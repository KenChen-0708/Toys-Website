<?php
require __DIR__ . '/helper/pdo_connection.php';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO Shipping_Address (member_id, address_line1, address_line2, city, state, postal_code, country) VALUES (?,?,?,?,?,?,?)'
    );

    $addr = ['999 Test Rd','Unit A','Kuala Lumpur','WP','50000','Malaysia'];

    $stmt->execute([1,$addr[0],$addr[1],$addr[2],$addr[3],$addr[4],$addr[5]]);
    echo "Insert #1 (member 1): OK\n";

    try {
        $stmt->execute([1,$addr[0],$addr[1],$addr[2],$addr[3],$addr[4],$addr[5]]);
        echo "Insert #2 (member 1 duplicate): OK (unexpected)\n";
    } catch (PDOException $e) {
        echo "Insert #2 (member 1 duplicate): DUPLICATE as expected: ", $e->getCode(), " - ", $e->getMessage(), "\n";
    }

    $stmt->execute([2,$addr[0],$addr[1],$addr[2],$addr[3],$addr[4],$addr[5]]);
    echo "Insert #3 (member 2 same address): OK\n";

    $pdo->commit();
} catch (Throwable $t) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: ", $t->getMessage(), "\n";
}
