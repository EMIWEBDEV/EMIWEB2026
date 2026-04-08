<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeFullController extends Command
{
    /**
     * Nama dan signature dari command.
     * {name}      : Nama Modul/Folder (Contoh: Packaging)
     * {developer} : Nama Developer (Contoh: frans)
     */
    protected $signature = 'make:fullcontroller {name} {developer}';

    /**
     * Deskripsi command.
     */
    protected $description = 'Membuat controller & routes per modul, lalu mendaftarkan require-nya ke file developer di routes/developer/';

    public function handle()
    {
        $name = $this->argument('name');
        $developer = $this->argument('developer');

        // === 1. Validasi Input ===
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error('Nama modul (name) harus diawali huruf besar (PascalCase). Contoh: Packaging');
            return 1;
        }

        // === 2. Buat Folder & File Controller ===
        $controllerFolder = app_path("Http/Controllers/{$name}");
        if (!is_dir($controllerFolder)) {
            mkdir($controllerFolder, 0755, true);
        }

        // Buat Resource Controller (Web)
        $this->call('make:controller', [
            'name' => "{$name}/{$name}Controller",
            '-r' => true,
        ]);

        // Buat API Controller
        $this->call('make:controller', [
            'name' => "{$name}/{$name}Api",
            '--api' => true,
        ]);

        $this->info("Controllers berhasil dibuat di: App\Http\Controllers\\{$name}");

        // === 3. Buat Folder & File Routes per Modul ===
        $routesModulFolder = base_path("routes/{$name}");
        if (!is_dir($routesModulFolder)) {
            mkdir($routesModulFolder, 0755, true);
        }

        $webRoutesFile = $routesModulFolder . "/{$name}Web.php";
        $apiRoutesFile = $routesModulFolder . "/{$name}Api.php";
        $lowerName = strtolower($name);

        // Template Web Routes
        if (!file_exists($webRoutesFile)) {
            $webContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Http\Controllers\\{$name}\\{$name}Controller;\n\nRoute::resource('{$lowerName}', {$name}Controller::class);\n";
            File::put($webRoutesFile, $webContent);
        }

        // Template API Routes
        if (!file_exists($apiRoutesFile)) {
            $apiContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Http\Controllers\\{$name}\\{$name}Api;\n\nRoute::prefix('api')->group(function() {\n    Route::apiResource('{$lowerName}', {$name}Api::class)->names('api.{$lowerName}');\n});\n";
            File::put($apiRoutesFile, $apiContent);
        }

        $this->info("File routes modul berhasil dibuat di: routes/{$name}/");

        // === 4. Injeksi 'require' ke File Developer ===
        $devFolder = base_path('routes/developer');
        if (!is_dir($devFolder)) {
            mkdir($devFolder, 0755, true);
        }

        $routeFileName = "{$developer}DevEvo.php";
        $devRouteFile = $devFolder . "/{$routeFileName}";

        // Jika file developer belum ada, buat file baru
        if (!file_exists($devRouteFile)) {
            File::put($devRouteFile, "<?php\n\n// Routes milik {$developer}\n");
            $this->info("File developer baru dibuat: routes/developer/{$routeFileName}");
        }

        // Siapkan baris require
        $requireWeb = "require base_path('routes/{$name}/{$name}Web.php');";
        $requireApi = "require base_path('routes/{$name}/{$name}Api.php');";

        $devRouteContent = File::get($devRouteFile);
        $appended = false;

        // Cek dan tambahkan Web Route
        if (strpos($devRouteContent, $requireWeb) === false) {
            File::append($devRouteFile, "\n" . $requireWeb);
            $appended = true;
        }

        // Cek dan tambahkan API Route
        if (strpos($devRouteContent, $requireApi) === false) {
            File::append($devRouteFile, "\n" . $requireApi);
            $appended = true;
        }

        if ($appended) {
            $this->info("✅ Baris require berhasil ditambahkan ke routes/developer/{$routeFileName}");
        } else {
            $this->line("ℹ️ Require sudah ada di routes/developer/{$routeFileName}, melewati proses append.");
        }

        $this->info('🎉 Selesai! Modul ' . $name . ' siap dikerjakan.');
    }
}