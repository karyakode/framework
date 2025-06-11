<?php

namespace Kodhe\Pulen\Framework\Loader\View;

use Kodhe\Pulen\Framework\Modules\Module;

class Blade
{
    // Constants and Properties
    public $blade_extension = '.blade.php';
    public $cache_time = 3600;
    
    protected $_compilers = [
        'comments', 'echos', 'forelse', 'empty', 'includes', 'layouts', 'extends',
        'section_start', 'section_end', 'yields', 'yield_sections', 'extensions',
        'else', 'unless', 'endunless', 'endforelse', 'structure_openings', 'structure_closings',
    ];
    
    protected $_last_section = [];
    protected $_sections = [];
    protected $_extensions = [];
    protected $_data = [];
    
    private $type = "view";

    // Magic Methods
    public function __construct() {}
    
    public function __set($name, $value) { 
        $this->_data[$name] = $value; 
    }
    
    public function __unset($name) { 
        unset($this->_data[$name]); 
    }
    
    public function __get($name) {
        return $this->_data[$name] ?? kodhe()->$name;
    }

    // Data Management Methods
    public function set($name, $value): self {
        $this->_data[$name] = $value;
        return $this;
    }

    public function append($name, $value): self {
        if (is_array($this->_data[$name])) {
            $this->_data[$name][] = $value;
        } else {
            $this->_data[$name] .= $value;
        }
        return $this;
    }

    public function set_data(array $data): self {
        $this->_data = array_merge($this->_data, $data);
        return $this;
    }

    // Compiler Extensions
    public function extend($compiler): self {
        $this->_extensions[] = $compiler;
        return $this;
    }

    // Main Rendering Methods
    public function render($template, $data = null, bool $isView = true, bool $return = false): string {
        $this->type = $isView ? "view" : "string";
        
        if ($data) {
            $this->set_data(is_object($data) ? json_decode(json_encode($data), true) : $data);
        }
        
        $compiled = $this->_compile($template);
        $content = $this->_run($compiled, $this->_data);

        if (!$return) {
            $this->output->append_output($content);
        }

        return $content;
    }

    // View Handling Methods
    protected function _find_view($view): string {
        $view = str_replace('.', '/', $view);
        $full_path = resolve_path(APPPATH, 'views') . '/' . $view . $this->blade_extension;

        if (method_exists($this->router, 'fetch_module')) {
            list($path, $_view) = Module::find($view . $this->blade_extension, 'views/');
            if ($path) $full_path = $path . $_view;
        }

        if (!file_exists($full_path) && $this->type === "view") {
            show_error('[Blade] Unable to find view: ' . $view);
        }

        return $full_path;
    }

    protected function _compile($template): string {
        $view_path = $this->_find_view($template);
        $cache_id = $this->type === "string" ? "blade-stringToBlade" : 'blade-' . md5($view_path);

        if ($compiled = $this->cache->file->get($cache_id)) {
            if (ENVIRONMENT === 'production') return $compiled;
            if ($this->type === "view") {
                $meta = $this->cache->file->get_metadata($cache_id);
                if ($meta['mtime'] > filemtime($view_path)) return $compiled;
            }
        }

        $template = $this->type === "view" ? file_get_contents($view_path) : $template;

        foreach ($this->_compilers as $compiler) {
            $method = "_compile_{$compiler}";
            $template = $this->$method($template);
        }

        $this->cache->file->save($cache_id, $template, $this->cache_time);
        return $template;
    }

    protected function _run($template, $data = []): string {
        extract($data);
        ob_start();
        eval(' ?>' . $template . '<?php ');
        return ob_get_clean();
    }

    // Template Inclusion Methods
    protected function _include($template, $data = null): string {
        $data = $data ? array_merge($this->_data, $data) : $this->_data;
        $compiled = $this->_compile($template);
        return $this->_run($compiled, $data);
    }

    // Section Handling Methods
    protected function _yield_old($section): string {
        return $this->_sections[$section] ?? '';
    }

    protected function _yield(string $section, $default = '') {
        if (isset($this->_sections[$section])) {
            return $this->_sections[$section];
        }
        return $default;
    }

    protected function _section_start($section): void {
        $this->_last_section[] = $section;
        ob_start();
    }

    protected function _section_end(): string {
        $last = array_pop($this->_last_section);
        $this->_section_extend($last, ob_get_clean());
        return $last;
    }

    protected function _section_extend($section, $content): void {
        $this->_sections[$section] = isset($this->_sections[$section])
            ? str_replace('@parent', $content, $this->_sections[$section])
            : $content;
    }

    // Compiler Utility Methods
    public function matcher($function): string {
        return '/(\s*)@' . $function . '(\s*\(.*\))/';
    }

    // Core Compiler Methods
    protected function _compile_comments($value): string {
        $value = preg_replace('/\{\{--(.+?)(--\}\})?\n/', "<?php // $1 ?>", $value);
        return preg_replace('/\{\{--((.|\s)*?)--\}\}/', "<?php /* $1 */ ?>\n", $value);
    }

    protected function _compile_echos($value): string {
        return preg_replace('/\{\{(.+?)\}\}/', '<?php echo $1; ?>', $value);
    }

    protected function _compile_forelse($value): string {
        preg_match_all('/(\s*)@forelse(\s*\(.*\))(\s*)/', $value, $matches);
        foreach ($matches[0] as $forelse) {
            preg_match('/\$[^\s]*/', $forelse, $variable);
            $if = "<?php if (count({$variable[0]}) > 0): ?>";
            $blade = preg_replace('/(\s*)@forelse(\s*\(.*\))/', '$1' . $if . '<?php foreach$2: ?>', $forelse);
            $value = str_replace($forelse, $blade, $value);
        }
        return $value;
    }

    protected function _compile_empty($value): string {
        return str_replace('@empty', '<?php endforeach; ?><?php else: ?>', $value);
    }

    protected function _compile_endforelse($value): string {
        return str_replace('@endforelse', '<?php endif; ?>', $value);
    }

    protected function _compile_structure_openings($value): string {
        $pattern = '/(\s*)@(if|elseif|foreach|for|while)(\s*\(.*\))/';
        return preg_replace($pattern, '$1<?php $2$3: ?>', $value);
    }

    protected function _compile_structure_closings($value): string {
        $pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';
        return preg_replace($pattern, '$1<?php $2; ?>$3', $value);
    }

    protected function _compile_else($value): string {
        if (strpos($value, "elseif") !== false) {
            return $value;
        }
        return preg_replace('/(\s*)@(else)(\s*)/', '$1<?php $2: ?>$3', $value);
    }

    protected function _compile_unless($value): string {
        $pattern = '/(\s*)@unless(\s*\(.*\))/';
        return preg_replace($pattern, '$1<?php if( ! ($2)): ?>', $value);
    }

    protected function _compile_endunless($value): string {
        return str_replace('@endunless', '<?php endif; ?>', $value);
    }

    protected function _compile_extensions($value): string {
        foreach ($this->_extensions as $compiler) {
            $value = call_user_func($compiler, $value);
        }
        return $value;
    }

    protected function _compile_includes($value): string {
        $pattern = static::matcher('include');
        return preg_replace($pattern, '$1<?php echo $this->_include$2; ?>', $value);
    }

    protected function _compile_layouts($value): string {
        $pattern = $this->matcher('layout');

        if (!preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
            return $value;
        }

        $value = preg_replace($pattern, '', $value);

        foreach ($matches as $set) {
            $value .= "\n" . $set[1] . '<?php echo $this->_include' . $set[2] . "; ?>\n";
        }

        return $value;
    }

    protected function _compile_extends($value): string {
        $pattern = $this->matcher('extends');

        if (!preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
            return $value;
        }

        $value = preg_replace($pattern, '', $value);

        foreach ($matches as $set) {
            $value .= "\n" . $set[1] . '<?php echo $this->_include' . $set[2] . "; ?>\n";
        }

        return $value;
    }

    protected function _compile_yields_old($value): string {
        $pattern = $this->matcher('yield');

        return preg_replace_callback($pattern, function ($matches) {
            if (preg_match('/\(\s*\'([^\']+)\'\s*,\s*([^\)]+)\s*\)/', $matches[2], $params)) {
                $section = $params[1];
                $default = $params[2];
                return "<?php echo isset(\$this->_sections['$section']) ? \$this->_sections['$section'] : $default; ?>";
            }

            return "<?php echo \$this->_yield{$matches[2]}; ?>";
        }, $value);
    }

    protected function _compile_yields(string $value): string {
        return preg_replace_callback('/@yield\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\)/', function ($matches) {
            $sectionName = $matches[1];
            $default = isset($matches[2]) ? trim($matches[2]) : "''";

            if (preg_match('/^([\'"]).*\1$/', $default)) {
                return "<?php echo \$this->_yield('{$sectionName}', {$default}); ?>";
            } else {
                return "<?php echo \$this->_yield('{$sectionName}', {$default}); ?>";
            }
        }, $value);
    }

    protected function _compile_section_start($value): string {
        $pattern = $this->matcher('section');
        return preg_replace_callback($pattern, function ($matches) {
            if (preg_match('/\(\s*\'([^\']+)\'\s*,\s*([^\)]+)\s*\)/', $matches[2], $params)) {
                $section = $params[1];
                $content = $params[2];
                
                if (preg_match('/[\(\)\->\[]/', $content)) {
                    return "<?php \$this->_section_start('$section'); echo $content; \$this->_section_end(); ?>";
                } else {
                    return "<?php \$this->_section_start('$section'); echo $content; \$this->_section_end(); ?>";
                }
            }

            return "<?php \$this->_section_start{$matches[2]}; ?>";
        }, $value);
    }

    protected function _compile_section_end($value): string {
        $replace = '<?php $this->_section_end(); ?>';
        return str_replace('@endsection', $replace, $value);
    }

    protected function _compile_yield_sections($value): string {
        $replace = '<?php echo $this->_yield($this->_section_end()); ?>';
        return str_replace('@yield_section', $replace, $value);
    }
}