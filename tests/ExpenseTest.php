<?php
use PHPUnit\Framework\TestCase;

class ExpenseTest extends TestCase {
    private $pdo;
    private $user_id = 1;

    protected function setUp(): void {
        $this->pdo = new PDO('mysql:host=localhost;dbname=test', 'username', 'password');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        $this->pdo->exec("INSERT INTO users (id, username) VALUES ($this->user_id, 'testuser')");
    }

    public function testGetCategoryData() {
        // Sample data
        $this->pdo->exec("INSERT INTO expenses (user_id, description, amount, category, expense_date)
                         VALUES ($this->user_id, 'Lunch', 15.00, 'Food', '2023-01-01')");
        $this->pdo->exec("INSERT INTO expenses (user_id, description, amount, category, expense_date)
                         VALUES ($this->user_id, 'Dinner', 25.00, 'Food', '2023-01-02')");
        $this->pdo->exec("INSERT INTO expenses (user_id, description, amount, category, expense_date)
                         VALUES ($this->user_id, 'Bus', 5.00, 'Transport', '2023-01-03')");

        $stmt = $this->pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY category");
        $stmt->execute([$this->user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Export CSV
        $this->exportToCSV($results, __DIR__ . '/test_export.csv');

        $this->assertCount(2, $results);
    }

    protected function tearDown(): void {
        $this->pdo->rollBack();
    }

    private function exportToCSV(array $data, string $filename = 'test_export.csv') {
        $file = fopen($filename, 'w');

        if (!empty($data)) {
            fputcsv($file, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }

        fclose($file);
    }
}
