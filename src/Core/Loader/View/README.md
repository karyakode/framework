# Blade Templating for CodeIgniter 3

This library brings Laravel's Blade templating engine features to CodeIgniter 3. It simplifies view rendering by enabling Blade syntax, improving readability, and providing dynamic template capabilities.

## Features
- Blade-like syntax (`@if`, `@foreach`, `@yield`, `@section`, etc.).
- Simple integration with CodeIgniter 3.
- Caching for faster rendering.
- Support for HMVC (Hierarchical Model-View-Controller).
- Extendable with custom compilers.

---

## Installation

1. **Download the Library**
   Clone or download this repository and copy the `Blade.php` file into your CodeIgniter `application/libraries/` directory.

2. **Enable the Library**
   Load the library in your controller or autoload it:
   ```php
   $this->load->library('Blade');
   ```

3. **Configure Cache Directory**
   Ensure your `application/cache/` directory is writable. Blade uses this directory to store compiled templates.

---

## Usage

### Create a Blade Template
Create a `.blade.php` file in the `application/views` directory. For example, `welcome.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
</head>
<body>
    <h1>Welcome, {{ $name }}</h1>

    @if($is_logged_in)
        <p>You are logged in.</p>
    @else
        <p>Please log in.</p>
    @endif
</body>
</html>
```

### Render a Template in Controller
Use the `render` method to render the template with data:

```php
class Welcome extends CI_Controller {
    public function index() {
        $this->load->library('Blade');

        $data = [
            'title' => 'Home Page',
            'name' => 'John Doe',
            'is_logged_in' => true
        ];

        $this->blade->render('welcome', $data);
    }
}
```

### Template Inheritance
You can define layout templates and extend them in child templates.

#### Layout File (`layouts/main.blade.php`):
```blade
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title')</title>
</head>
<body>
    <header>
        <h1>My Website</h1>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <p>&copy; 2024 My Website</p>
    </footer>
</body>
</html>
```

#### Child Template (`home.blade.php`):
```blade
@extends('layouts.main')

@section('title', 'Home Page')

@section('content')
    <p>Welcome to the home page!</p>
@endsection
```

### Rendering the Child Template
In the controller:
```php
$this->blade->render('home');
```

---

## Advanced Features

### Adding Custom Compilers
You can add your custom Blade syntax by extending the library:
```php
$this->blade->extend(function($view) {
    return str_replace('@datetime', '<?php echo date("Y-m-d H:i:s"); ?>', $view);
});
```
Then use `@datetime` in your templates.

### Global Data
Set data globally to be available in all templates:
```php
$this->blade->set('app_name', 'My Application');
```
Use it in templates:
```blade
<p>Welcome to {{ $app_name }}</p>
```

---

## Troubleshooting

1. **Permission Errors**:
   Ensure the `application/cache/` directory is writable.

2. **Template Not Found**:
   Verify the template file name and location in `application/views`.

3. **Syntax Errors**:
   Ensure the Blade syntax is correct. Check Laravel's Blade documentation for reference.

---

## License
This library is open-source and licensed under the MIT License. Feel free to modify and use it in your projects.

---

## Contributing
Contributions are welcome! Feel free to submit issues or pull requests to improve this library.

---

## Acknowledgements
Inspired by Laravel's Blade templating engine. Special thanks to the CodeIgniter community for their support.
