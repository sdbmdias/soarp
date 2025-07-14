<?php
// Inicia a sessão em todas as páginas
session_start();

// 1. INCLUI A CONEXÃO COM O BANCO DE DADOS
// Agora a variável $conn estará disponível em todas as páginas que usarem este header.
require_once 'database.php';

// 2. BLOCO DE SEGURANÇA E AUTENTICAÇÃO
// Bloco de proteção para forçar redefinição de senha
if (isset($_SESSION['force_password_reset']) && basename($_SERVER['PHP_SELF']) != 'primeiro_acesso.php') {
    header('Location: primeiro_acesso.php');
    exit();
}

// Verifica se o usuário está logado (exceto na página de login e de primeiro acesso)
$paginas_publicas = ['index.php', 'primeiro_acesso.php'];
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), $paginas_publicas)) {
    header("Location: index.php");
    exit();
}

// 3. DEFINIÇÃO DE PERFIS DE USUÁRIO
// Define as variáveis de perfil para serem usadas em qualquer página
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';
$isPiloto = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'piloto';

// Define o nome do perfil para exibição no menu
$nome_perfil = '';
if (isset($_SESSION['user_type'])) {
    $nome_perfil = ucfirst($_SESSION['user_type']); // Deixa a primeira letra maiúscula (Administrador/Piloto)
}

// 4. LÓGICA DE VERIFICAÇÃO DE ALERTAS
// Centralizamos a verificação aqui para que o menu sempre saiba se deve exibir o alerta
$existem_alertas = false;
if ($isAdmin) { // Apenas administradores veem os alertas no menu
    $sql_alerts = "SELECT COUNT(id) AS total_alertas FROM aeronaves WHERE validade_sisant <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
    $result_alerts = $conn->query($sql_alerts);
    if ($result_alerts && $result_alerts->num_rows > 0) {
        $row_alerts = $result_alerts->fetch_assoc();
        if ($row_alerts['total_alertas'] > 0) {
            $existem_alertas = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;min-height:100vh;background-color:#f0f2f5;color:#333}
        .sidebar{width:250px;background-color:#2c3e50;color:#fff;padding-top:20px;box-shadow:2px 0 5px rgba(0,0,0,.1);display:flex;flex-direction:column;align-items:center}
        .sidebar ul{list-style:none;padding:0;width:100%;flex-grow:1}
        .sidebar ul li a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;transition:background-color .3s ease;font-size:16px;line-height:1.2}
        .sidebar ul li a i{margin-right:15px;font-size:20px;width:25px;text-align:center}
        .sidebar ul li a:hover,.sidebar ul li a.active{background-color:#34495e;border-left:5px solid #3498db;padding-left:15px}
        .menu-alert{color:#e74c3c!important;font-weight:700}
        .sidebar ul li.has-submenu>a{position:relative}
        .sidebar ul li.has-submenu>a .submenu-arrow{position:absolute;right:20px;transition:transform .3s ease}
        .sidebar ul li.has-submenu.open>a .submenu-arrow{transform:rotate(180deg)}
        .sidebar ul li .submenu{list-style:none;padding:0;max-height:0;overflow:hidden;transition:max-height .3s ease-out;background-color:#34495e}
        .sidebar ul li .submenu li a{padding:10px 20px 10px 45px;font-size:.95em;background-color:transparent}
        .sidebar ul li .submenu li a:hover,.sidebar ul li .submenu li a.active{background-color:#3f5872;border-left:5px solid #3498db}
        .sidebar ul li .submenu.open{max-height:400px} /* Aumentado para caber mais itens */

        .main-content{flex-grow:1;padding:30px;background-color:#f0f2f5;color:#333}
        .main-content h1{color:#2c3e50;margin-bottom:20px}
        .success-message-box,.error-message-box{padding:15px;border-radius:5px;text-align:center;font-weight:700;margin-bottom:20px;width:80%;max-width:700px;margin-left:auto;margin-right:auto;box-shadow:0 2px 5px rgba(0,0,0,.1);opacity:1;transition:opacity .5s ease-out 3s}
        .success-message-box{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .error-message-box{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}

        .form-container, .table-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            max-width: 90%;
            margin: auto;
            overflow-x: auto;
        }

        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:8px;font-weight:700;color:#555}
        .form-group input,.form-group select,.form-group textarea{width:calc(100% - 22px);padding:10px;border:1px solid #ccc;border-radius:5px;font-size:16px;color:#333}
        .form-group select:disabled{background-color:#e9ecef}
        .form-group textarea{resize:vertical;min-height:80px}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#3498db;outline:0;box-shadow:0 0 5px rgba(52,152,219,.5)}
        .form-actions{grid-column:1/-1;text-align:right;padding-top:20px}
        .form-actions button{color:#fff;padding:12px 25px;border:none;border-radius:5px;cursor:pointer;font-size:17px;transition:background-color .3s ease}
        .form-actions button[type="submit"]{background-color:#28a745}
        .form-actions button[type="submit"]:hover{background-color:#218838}
        .form-actions button:disabled{background-color:#a5d6a7;cursor:not-allowed}
        .data-table{width:100%;border-collapse:collapse;margin-top:15px}

        .data-table th, .data-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            text-align: center;
            color: #555;
            font-size: .9em;
            vertical-align: middle;
        }

        .data-table th{background-color:#f8f8f8;font-weight:700;color:#2c3e50}
        .data-table tbody tr:hover{background-color:#f5f5f5}
        .action-buttons a{display:inline-block;padding:5px 10px;margin-right:5px;border-radius:4px;text-decoration:none;color:#fff;font-size:.85em}
        .action-buttons .edit-btn{background-color:#007bff}

        /* --- Estilos Padronizados para Status --- */
        .status-ativo { color: #28a745; font-weight: 700; }
        .status-em_manutencao, .status-afastado { color: #ffc107; font-weight: 700; }
        .status-baixada, .status-desativado { color: #dc3545; font-weight: 700; }
        .status-adida { color: #007bff; font-weight: 700; }

        .user-role-display {
            padding: 15px 20px;
            text-align: center;
            color: #bdc3c7;
            font-size: 14px;
            line-height: 1.4;
            border-top: 1px solid #4a627a;
            width: 100%;
            box-sizing: border-box;
        }
        .user-role-display p { margin: 0; }
        .user-role-display strong { color: #fff; font-size: 15px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="listar_pilotos.php"><i class="fas fa-users"></i> Pilotos</a></li>
            <li><a href="listar_aeronaves.php"><i class="fas fa-plane"></i> Aeronaves</a></li>
            <li><a href="listar_controles.php"><i class="fas fa-gamepad"></i> Controles</a></li>
            <li><a href="manutencao.php"><i class="fas fa-tools"></i> Manutenção</a></li>
            <li><a href="checklist.php"><i class="fas fa-check-square"></i> Checklist/Documentos</a></li>
            <li><a href="listar_missoes.php"><i class="fas fa-map-marked-alt"></i> Missões</a></li>
            <li><a href="#"><i class="fas fa-file-pdf"></i> Relatórios</a></li>

            <?php if ($isAdmin): ?>
            <li class="has-submenu" id="admin-menu">
                <a href="#" id="admin-menu-toggle"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu" id="admin-submenu">
                    <li><a href="cadastro_aeronaves.php">Cadastro de Aeronaves</a></li>
                    <li><a href="cadastro_pilotos.php">Cadastro de Pilotos</a></li>
                    <li><a href="cadastro_controles.php">Cadastro de Controles</a></li>
                    <li><a href="cadastro_modelos.php">Cadastro de Modelos</a></li>
                    <li><a href="cadastro_crbm_obm.php">Cadastro de CRBM/OBM</a></li>
                    <li><a href="gerenciar_documentos.php">Gerenciar Documentos</a></li>
                    <li><a href="alertas.php" class="<?php if ($existem_alertas) echo 'menu-alert'; ?>">Alertas</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="logout.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>

        <?php if (!empty($nome_perfil)): ?>
        <div class="user-role-display">
            <p>Você está logado como:<br><strong><?php echo htmlspecialchars($nome_perfil); ?></strong></p>
        </div>
        <?php endif; ?>
    </div>