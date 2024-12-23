# Kodhe Framework  

Kodhe Framework adalah pengembangan modern dari CodeIgniter 3 yang dirancang untuk memberikan pengalaman pengembangan aplikasi yang lebih efisien, modular, dan sesuai dengan standar modern. Kodhe Framework mendukung namespace, dependency injection (DI), HMVC, dan templating modern seperti Blade Laravel.  

---

## Fitur Utama  

1. **Dukungan Namespace**  
   Kodhe Framework menggunakan namespace untuk struktur yang lebih rapi:
   - `app`
     - `Controllers`  
     - `Models`  
     - `Libraries`  
     - `Services`  

3. **Dependency Injection (DI)**  
   Mendukung DI untuk controller dan service, mempermudah manajemen dependensi.  
   ```php
   // DI di Controller
   public function __construct(App\Services\PostServices $postService) {  
       $this->postService = $postService;  
   }

4. **HMVC (Hierarchical Model-View-Controller)**
   Mendukung arsitektur modular untuk mempermudah pengembangan aplikasi berskala besar.

   - `app`
      - `Modules/Blog`
        - `Controllers`
        - `Models`
        - `Views`


5. **Blade Template**
   Menggunakan library mirip Blade Laravel untuk templating.
   ```php
   $this->blade->render('post', $data);

6. **Services**
   Memisahkan logika bisnis dari controller untuk kode yang lebih bersih dan terorganisir.
   ```php
   namespace App\Services;  
   class PostServices {  
       public function getAllPosts() {  
           // Logika bisnis  
       }  
   }


7. **Dukungan Default Super Object CodeIgniter 3**
  Tetap mendukung fitur bawaan CI3 seperti $this->load, $this->input, $this->db, dll.

8. **Berbasis CodeIgniter 3**
  Kodhe Framework sepenuhnya kompatibel dengan fitur-fitur dasar CodeIgniter 3.

9. **Standar Penamaan File dan Folder**

  Nama folder dan file diawali huruf besar (contoh: PostModel.php).
  Penamaan class tidak menggunakan tanda underscore _ (contoh: PostModel, bukan post_model).
