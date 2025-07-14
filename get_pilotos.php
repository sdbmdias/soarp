<?php
require_once 'includes/database.php';
session_start();

// Garante que apenas usuários logados possam acessar
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    echo json_encode(['error' => 'Acesso negado. Faça o login.']);
    exit();
}

$crbm = isset($_GET['crbm']) ? $_GET['crbm'] : '';

if (empty($crbm)) {
    http_response_code(400); // Requisição Inválida
    echo json_encode(['error' => 'CRBM não fornecido.']);
    exit();
}

$pilotos = [];
// CORREÇÃO: Usa os nomes corretos das colunas: `posto_graduacao` e `nome_completo`
$sql = "SELECT id, nome_completo, posto_graduacao FROM pilotos WHERE status_piloto = 'ativo' AND crbm_piloto = ? ORDER BY posto_graduacao, nome_completo ASC";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $crbm);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pilotos[] = [
            'id' => $row['id'],
            // CORREÇÃO: Usa as chaves corretas do array
            'nome_completo' => htmlspecialchars($row['posto_graduacao'] . ' ' . $row['nome_completo'])
        ];
    }
    $stmt->close();
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($pilotos);
?>