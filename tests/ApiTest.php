<?php
// Pastikan autoload Composer dipanggil
require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private $conn;
    // Gunakan nama DB test yang sama dengan yang diatur di GitHub Actions
    private $testDbName = 'db_kontak_test'; 

    // Helper: Fungsi validate_input dari api.php Anda harus diduplikasi di sini
    private function validate_input($data){
        $errors = [];
        if(empty($data['nama'])){ $errors[] = 'Nama tidak boleh kosong'; }
        if(empty($data['telepon']) || !preg_match('/^[0-9]+$/', $data['telepon'])){ $errors[] = 'Nomor Telepon hanya boleh angka'; }
        
        if(!empty($data['email'])){
            if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)){ $errors[] = 'Format email tidak valid'; } 
            elseif(!preg_match('/@gmail\.com$/', $data['email'])){ $errors[] = 'Format email tidak valid'; } // Tambahan validasi @gmail.com dari kode Anda
        }
        return $errors;
    }

    protected function setUp(): void
    {
        // 1. Koneksi menggunakan environment GitHub Actions
        $dbServer = getenv('DB_SERVER') ?: 'localhost';
        $dbUser = getenv('DB_USERNAME') ?: 'root';
        $dbPassword = getenv('DB_PASSWORD') ?: '';
        
        // Di lokal, gunakan DB_NAME dari api.php, tetapi untuk test, kita pakai db_kontak_test
        $this->conn = new mysqli($dbServer, $dbUser, $dbPassword, $this->testDbName);
        
        if ($this->conn->connect_error) {
            $this->markTestSkipped('Koneksi ke database test gagal: ' . $this->conn->connect_error);
        }

        // 2. Bersihkan tabel sebelum setiap test (TRUNCATE)
        $this->conn->query("TRUNCATE TABLE kontak");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    // =================================================================
    // TEST 1: PENGUJIAN VALIDASI (Login)
    // =================================================================

    public function testValidasiInputSukses()
    {
        // Memastikan fungsi validasi mengembalikan status "berhasil" (tidak ada error)
        $data = ['nama' => 'Test Sukses', 'telepon' => '081234', 'email' => 'sukses@gmail.com'];
        $this->assertEmpty($this->validate_input($data), "Validasi seharusnya sukses");
    }

    public function testValidasiInputGagalNamaTelepon()
    {
        // Memastikan fungsi validasi mengembalikan status "gagal"
        $data = ['nama' => '', 'telepon' => 'abc', 'email' => 'gagal@gmail.com'];
        $errors = $this->validate_input($data);
        $this->assertContains('Nama tidak boleh kosong', $errors);
        $this->assertContains('Nomor Telepon hanya boleh angka', $errors);
    }
    
    // =================================================================
    // TEST 2, 3, 4, 5: PENGUJIAN CRUD
    // =================================================================
    
    public function testA_CreateKontak() // A_ agar di running duluan
    {
        $data = ['nama' => 'User Baru', 'telepon' => '111222333', 'email' => 'userbaru@gmail.com'];

        // Simulasi Insert: Memastikan data berhasil disimpan
        $sql = "INSERT INTO kontak (nama, telepon, email) VALUES (?,?,?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $data['nama'], $data['telepon'], $data['email']);
        $this->assertTrue($stmt->execute(), "Gagal mengeksekusi INSERT kontak baru");
        
        // Verifikasi
        $check = $this->conn->query("SELECT * FROM kontak WHERE nama='User Baru'");
        $this->assertEquals(1, $check->num_rows, "Data baru seharusnya tersimpan");
    }

    public function testReadKontak()
    {
        // Masukkan data dummy
        $this->conn->query("INSERT INTO kontak (nama, telepon) VALUES ('Data Baca 1', '100')");
        
        // Simulasi Read: Memastikan ada data yang dikembalikan
        $result = $this->conn->query("SELECT * FROM kontak");
        $this->assertGreaterThanOrEqual(1, $result->num_rows, "Seharusnya ada 1 atau lebih kontak yang terbaca");
        
        $row = $result->fetch_assoc();
        $this->assertEquals('Data Baca 1', $row['nama']);
    }

    public function testUpdateKontak()
    {
        // 1. Masukkan data dummy untuk diupdate
        $this->conn->query("INSERT INTO kontak (id, nama, telepon) VALUES (999, 'Nama Lama', '123')");

        // 2. Simulasi Update: Memastikan data berhasil diubah
        $updateData = ['id' => 999, 'nama' => 'Nama Baru', 'telepon' => '456', 'email' => 'baru@gmail.com'];
        $sql = "UPDATE kontak SET nama = ?, telepon = ?, email = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssi", $updateData['nama'], $updateData['telepon'], $updateData['email'], $updateData['id']);
        $this->assertTrue($stmt->execute(), "Gagal mengeksekusi UPDATE kontak");

        // 3. Verifikasi perubahan
        $check = $this->conn->query("SELECT nama, telepon FROM kontak WHERE id=999")->fetch_assoc();
        $this->assertEquals('Nama Baru', $check['nama']);
    }

    public function testDeleteKontak()
    {
        // 1. Masukkan data dummy untuk dihapus
        $this->conn->query("INSERT INTO kontak (id, nama, telepon) VALUES (888, 'Data Hapus', '789')");

        // 2. Simulasi Delete: Memastikan data berhasil dihapus
        $deleteId = 888;
        $sql = "DELETE FROM kontak WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $deleteId);
        $this->assertTrue($stmt->execute(), "Gagal mengeksekusi DELETE kontak");
        
        // 3. Verifikasi data sudah hilang
        $check = $this->conn->query("SELECT * FROM kontak WHERE id=888");
        $this->assertEquals(0, $check->num_rows, "Kontak seharusnya sudah terhapus");
    }
}