<?php
session_start(); // Inicia a sessão

// Bloco de proteção para forçar redefinição de senha
if (isset($_SESSION['force_password_reset'])) {
    header('Location: primeiro_acesso.php');
    exit();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Redireciona para a página de login
    exit();
}

// Define os tipos de usuário para controle de acesso
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';
$isPiloto = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'piloto';

// Define o nome do perfil para exibição
$nome_perfil = '';
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] == 'administrador') {
        $nome_perfil = 'Administrador';
    } elseif ($_SESSION['user_type'] == 'piloto') {
        $nome_perfil = 'Piloto';
    }
}

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

// --- Início da verificação de Alertas ---
$existem_alertas = false;
$sql_alerts = "SELECT COUNT(id) AS total_alertas FROM aeronaves WHERE validade_sisant <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
$result_alerts = $conn->query($sql_alerts);
if ($result_alerts && $result_alerts->num_rows > 0) {
    $row_alerts = $result_alerts->fetch_assoc();
    if ($row_alerts['total_alertas'] > 0) {
        $existem_alertas = true;
    }
}
// --- Fim da verificação de Alertas ---


// Variáveis para os painéis
$total_pilotos = 0;
$total_aeronaves = 0;
$total_aeronaves_crbm = 0;

// Busca a contagem total de aeronaves (usada por ambos os perfis)
$sql_total_aeronaves = "SELECT COUNT(id) AS total_aeronaves FROM aeronaves";
$result_total_aeronaves = $conn->query($sql_total_aeronaves);
if ($result_total_aeronaves->num_rows > 0) {
    $total_aeronaves = $result_total_aeronaves->fetch_assoc()['total_aeronaves'];
}

// Lógica condicional para buscar dados específicos de cada perfil
if ($isAdmin) {
    $sql_total_pilotos = "SELECT COUNT(id) AS total_pilotos FROM pilotos";
    $result_total_pilotos = $conn->query($sql_total_pilotos);
    if ($result_total_pilotos->num_rows > 0) {
        $total_pilotos = $result_total_pilotos->fetch_assoc()['total_pilotos'];
    }
} elseif ($isPiloto) {
    $crbm_do_usuario_logado = '';
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    if ($stmt_crbm->execute()) {
        $result_crbm = $stmt_crbm->get_result();
        if ($result_crbm->num_rows > 0) {
            $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
        }
    }
    $stmt_crbm->close();

    if (!empty($crbm_do_usuario_logado)) {
        $stmt_acft_crbm = $conn->prepare("SELECT COUNT(id) AS total FROM aeronaves WHERE crbm = ?");
        $stmt_acft_crbm->bind_param("s", $crbm_do_usuario_logado);
        if ($stmt_acft_crbm->execute()) {
            $result_acft_crbm = $stmt_acft_crbm->get_result();
            $total_aeronaves_crbm = $result_acft_crbm->fetch_assoc()['total'];
        }
        $stmt_acft_crbm->close();
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif; margin: 0; padding: 0; display: flex;
            min-height: 100vh; background-color: #f0f2f5; color: #333;
        }
        .sidebar {
            width: 250px; background-color: #2c3e50; color: white; padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column;
            align-items: center;
        }
        .sidebar ul {
            list-style: none; padding: 0; width: 100%;
        }
        .sidebar ul li a {
            display: flex; align-items: center; padding: 15px 20px; color: white;
            text-decoration: none; transition: background-color 0.3s ease;
            font-size: 16px; line-height: 1.2;
        }
        .sidebar ul li a i { margin-right: 15px; font-size: 20px; width: 25px; text-align: center; }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background-color: #34495e; border-left: 5px solid #3498db; padding-left: 15px;
        }
        .menu-alert { color: #e74c3c !important; font-weight: bold; }
        .sidebar ul li.has-submenu > a { position: relative; }
        .sidebar ul li.has-submenu > a .submenu-arrow { position: absolute; right: 20px; transition: transform 0.3s ease; }
        .sidebar ul li.has-submenu > a .submenu-arrow.fa-chevron-up { transform: rotate(180deg); }
        .sidebar ul li .submenu {
            list-style: none; padding: 0; max-height: 0; overflow: hidden;
            transition: max-height 0.3s ease-out; background-color: #34495e;
        }
        .sidebar ul li .submenu li a { padding: 10px 20px 10px 45px; font-size: 0.95em; background-color: transparent; }
        .sidebar ul li .submenu li a:hover, .sidebar ul li .submenu li a.active {
            background-color: #3f5872; border-left: 5px solid #3498db;
        }
        .sidebar ul li .submenu.open { max-height: 250px; }
        
        .user-role-display {
            padding: 15px 20px; text-align: center; color: #bdc3c7;
            font-size: 14px; line-height: 1.4; margin-top: 15px;
            border-top: 1px solid #4a627a;
        }
        .user-role-display p { margin: 0; }
        .user-role-display strong { color: #ffffff; font-size: 15px; }

        .main-content { flex-grow: 1; padding: 30px; background-color: #f0f2f5; }
        .main-content h1 { color: #2c3e50; margin-bottom: 30px; }
        .dashboard-cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px; margin-bottom: 40px;
        }
        .card {
            background-color: #ffffff; padding: 25px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12); }
        .card h2 { color: #3498db; margin-bottom: 15px; font-size: 22px; }
        .card p { font-size: 36px; font-weight: bold; color: #2c3e50; margin: 0; }
        .card.pilots p { color: #28a745; }
        .card.aeronaves p { color: #34495e; }
        .recent-flights {
            background-color: #ffffff; padding: 25px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        .recent-flights h2 { color: #2c3e50; margin-bottom: 20px; font-size: 22px; }
        .recent-flights table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .recent-flights table th, .recent-flights table td {
            padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; color: #555;
        }
        .recent-flights table th { background-color: #f8f8f8; font-weight: bold; color: #2c3e50; }
        .recent-flights table tr:last-child td { border-bottom: none; }
        .recent-flights table tbody tr:hover { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="listar_pilotos.php"><i class="fas fa-users"></i> Pilotos</a></li>
            <li><a href="listar_aeronaves.php"><i class="fas fa-plane"></i> Aeronaves</a></li>
            <li><a href="listar_controles.php"><i class="fas fa-gamepad"></i> Controles</a></li>
            <li><a href="manutencao.php"><i class="fas fa-tools"></i> Manutenção</a></li>
            <li><a href="#"><i class="fas fa-map-marked-alt"></i> Missões Realizadas</a></li>
            <li><a href="#"><i class="fas fa-file-pdf"></i> Relatório em PDF</a></li>
            
            <?php if ($isAdmin): ?>
            <li class="has-submenu">
                <a href="#" id="admin-menu-toggle"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="cadastro_aeronaves.php"> Cadastro de Aeronaves</a></li>
                    <li><a href="cadastro_pilotos.php"> Cadastro de Pilotos</a></li>
                    <li><a href="cadastro_controles.php"> Cadastro de Controles</a></li>                
                    <li><a href="alertas.php" class="<?php if ($existem_alertas) echo 'menu-alert'; ?>"> Alertas</a></li> 
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="index.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>

            <?php if (!empty($nome_perfil)): ?>
            <li class="user-role-display">
                <p>Você está logado como:<br><strong><?php echo $nome_perfil; ?></strong></p>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="main-content">
        <h1>Dashboard</h1>

        <div class="dashboard-cards">
            <?php if ($isAdmin): ?>
                <div class="card">
                    <h2>Horas Voadas (Total)</h2>
                    <p>1245 <small>horas</small></p>
                </div>           
                <div class="card">
                    <h2>Voos Realizados (Mês)</h2>
                    <p>87 <small>voos</small></p>
                </div>
                <div class="card pilots">
                    <h2>Pilotos Cadastrados</h2>
                    <p><?php echo $total_pilotos; ?> <small>pilotos</small></p>
                </div>
                <div class="card aeronaves">
                    <h2>Aeronaves Cadastradas</h2>
                    <p><?php echo $total_aeronaves; ?> <small>aeronaves</small></p>
                </div>

            <?php elseif ($isPiloto): ?>
                <div class="card">
                    <h2>Horas Voadas (Total)</h2>
                    <p>1245 <small>horas</small></p>
                </div>           
                <div class="card">
                    <h2>Voos Realizados (Mês)</h2>
                    <p>87 <small>voos</small></p>
                </div>
                <div class="card aeronaves">
                    <h2>Aeronaves no CRBM</h2>
                    <p><?php echo $total_aeronaves_crbm; ?> <small>aeronaves</small></p>
                </div>
                <div class="card aeronaves">
                    <h2>Aeronaves no CBMPR</h2>
                    <p><?php echo $total_aeronaves; ?> <small>aeronaves</small></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="recent-flights">
            <h2>Últimos Voos Realizados</h2>
            <table>
                <thead>
                    <tr>
                        <th>Drone</th>
                        <th>Piloto</th>
                        <th>Data</th>
                        <th>Duração</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Drone XYZ</td>
                        <td>João Silva</td>
                        <td>12/07/2025</td>
                        <td>2h 15min</td>
                        <td>Concluído</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const adminMenuToggle = document.getElementById('admin-menu-toggle');
            const adminSubmenu = document.querySelector('.has-submenu .submenu');
            const submenuArrow = document.querySelector('.has-submenu .submenu-arrow');

            if (adminMenuToggle && adminSubmenu) {
                // Abre o submenu se a página ativa estiver dentro dele
                if (adminSubmenu.querySelector('a.active')) {
                    adminSubmenu.classList.add('open');
                    if (submenuArrow) {
                        submenuArrow.classList.remove('fa-chevron-down');
                        submenuArrow.classList.add('fa-chevron-up');
                    }
                    adminMenuToggle.classList.add('active');
                }

                adminMenuToggle.addEventListener('click', function(event) {
                    event.preventDefault();
                    adminSubmenu.classList.toggle('open');
                    if (submenuArrow) {
                        submenuArrow.classList.toggle('fa-chevron-down');
                        submenuArrow.classList.toggle('fa-chevron-up');
                    }
                });
            }
        });
    </script>
</body>
</html>