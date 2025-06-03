<?php
function load_controller_with_dependencies($controllerClass){
  // Gunakan refleksi untuk mendapatkan parameter di konstruktor
  $reflector = new ReflectionClass($controllerClass);
  // Ambil konstruktor
  $constructor = $reflector->getConstructor();
  if (!$constructor) {
    return new $controllerClass;

    // Jika tidak ada konstruktor, kembalikan instance langsung
  }
  // Ambil parameter dari konstruktor
  $parameters = $constructor->getParameters();
  $dependencies = [];
  foreach ($parameters as $parameter) {
    // Ambil tipe parameter
    $type = $parameter->getType();
    if ($type && !$type->isBuiltin()) {
      $className = $type->getName();
      // Resolusi dependency (DI)
      if (class_exists($className)) {
        $dependencies[] = load_dependency($className);
      } else {
        throw new Exception("Class {$className} tidak ditemukan untuk parameter {$parameter->getName()}.");
      }
    }
  }

  // Buat instance controller dengan dependensi yang di-*resolve*
  return $reflector->newInstanceArgs($dependencies);
}

function load_dependency($className){
  $CI =& get_instance();
  // Pastikan model atau library dimuat jika dibutuhkan
  if ($CI->load->is_loaded($className)) {
    return $CI->{$className};
  }

  // Muat secara manual jika belum ada
  if (strpos($className, '_model') !== false) {
    $CI->load->model($className);
    return $CI->{$className};
  } elseif (strpos($className, 'Service') !== false) {
    $CI->load->library($className);
    // Atur library Anda
    return new $className();
  }

  // Kembalikan instance langsung jika kelas ada
  return new $className();
}
