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

// Define se o usuário é um administrador
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// Define o nome do perfil para exibição
$nome_perfil = 'Administrador';

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

// --- Início da verificação de Alertas ---
$existem_alertas = false;
$conn_alerts = new mysqli($servername, $username, $password, $dbname);
if (!$conn_alerts->connect_error) {
    $sql_alerts = "SELECT COUNT(id) AS total_alertas FROM aeronaves WHERE validade_sisant <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
    $result_alerts = $conn_alerts->query($sql_alerts);
    if ($result_alerts && $result_alerts->num_rows > 0) {
        $row_alerts = $result_alerts->fetch_assoc();
        if ($row_alerts['total_alertas'] > 0) {
            $existem_alertas = true;
        }
    }
    $conn_alerts->close();
}
// --- Fim da verificação de Alertas ---

$mensagem_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = htmlspecialchars($_POST['nome_completo']);
    $rg = htmlspecialchars($_POST['rg']);
    $cpf = htmlspecialchars($_POST['cpf']);
    $email = htmlspecialchars($_POST['email']);
    $telefone = htmlspecialchars($_POST['telefone']);
    $crbm_piloto = htmlspecialchars($_POST['crbm_piloto']);
    $obm_piloto = htmlspecialchars($_POST['obm_piloto']);
    $cadastro_sarpas = htmlspecialchars($_POST['cadastro_sarpas']);
    $cparp = htmlspecialchars($_POST['cparp']);
    $status_piloto = htmlspecialchars($_POST['status_piloto']);
    $info_adicionais_piloto = htmlspecialchars($_POST['info_adicionais_piloto']);
    $senha = $_POST['senha'];
    $tipo_usuario = htmlspecialchars($_POST['tipo_usuario']);

    // Por padrão, a senha inicial requer redefinição
    $senha_redefinida = 0;
    $senha_hashed = password_hash($senha, PASSWORD_DEFAULT);

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Falha na conexão com o banco de dados: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO pilotos (nome_completo, rg, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, senha, tipo_usuario, senha_redefinida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sssssssssssssi", $nome_completo, $rg, $cpf, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $senha_hashed, $tipo_usuario, $senha_redefinida);

    if ($stmt->execute()) {
        $mensagem_status = "<div id='status-message' class='success-message-box'>Piloto cadastrado com sucesso!</div>";
    } else {
        $mensagem_status = "<div id='status-message' class='error-message-box'>Erro ao cadastrar piloto: " . $stmt->error . "</div>";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Cadastro de Pilotos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;min-height:100vh;background-color:#f0f2f5;color:#333}.sidebar{width:250px;background-color:#2c3e50;color:#fff;padding-top:20px;box-shadow:2px 0 5px rgba(0,0,0,.1);display:flex;flex-direction:column;align-items:center}.sidebar ul{list-style:none;padding:0;width:100%}.sidebar ul li a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;transition:background-color .3s ease;font-size:16px;line-height:1.2}.sidebar ul li a i{margin-right:15px;font-size:20px;width:25px;text-align:center}.sidebar ul li a:hover,.sidebar ul li a.active{background-color:#34495e;border-left:5px solid #3498db;padding-left:15px}.menu-alert{color:#e74c3c!important;font-weight:700}.sidebar ul li.has-submenu>a{position:relative}.sidebar ul li.has-submenu>a .submenu-arrow{position:absolute;right:20px;transition:transform .3s ease}.sidebar ul li.has-submenu>a .submenu-arrow.fa-chevron-up{transform:rotate(180deg)}.sidebar ul li .submenu{list-style:none;padding:0;max-height:0;overflow:hidden;transition:max-height .3s ease-out;background-color:#34495e}.sidebar ul li .submenu li a{padding:10px 20px 10px 45px;font-size:.95em;background-color:transparent}.sidebar ul li .submenu li a:hover,.sidebar ul li .submenu li a.active{background-color:#3f5872;border-left:5px solid #3498db}.sidebar ul li .submenu.open{max-height:250px}.user-role-display{padding:15px 20px;text-align:center;color:#bdc3c7;font-size:14px;line-height:1.4;margin-top:15px;border-top:1px solid #4a627a}.user-role-display p{margin:0}.user-role-display strong{color:#fff;font-size:15px}.main-content{flex-grow:1;padding:30px;background-color:#f0f2f5;color:#333}.main-content h1{color:#2c3e50;margin-bottom:10px}.success-message-box,.error-message-box{padding:15px;border-radius:5px;text-align:center;font-weight:700;margin-bottom:20px;width:50%;margin-left:auto;margin-right:auto;box-shadow:0 2px 5px rgba(0,0,0,.1);opacity:1;transition:opacity .5s ease-out}.success-message-box{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}.error-message-box{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.cadastro-form-container{background-color:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);max-width:800px;margin:auto}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.form-group{margin-bottom:15px}.form-group label{display:block;margin-bottom:8px;font-weight:700;color:#555}.form-group input,.form-group select,.form-group textarea{width:calc(100% - 22px);padding:10px;border:1px solid #ccc;border-radius:5px;font-size:16px;color:#333}.form-group textarea{resize:vertical;min-height:80px}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#3498db;outline:0;box-shadow:0 0 5px rgba(52,152,219,.5)}.form-actions{grid-column:1/-1;text-align:right;padding-top:20px}.form-actions button{background-color:#28a745;color:#fff;padding:12px 25px;border:none;border-radius:5px;cursor:pointer;font-size:17px;transition:background-color .3s ease}.form-actions button:hover{background-color:#218838}.form-actions button:disabled{background-color:#a5d6a7;cursor:not-allowed}
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
            <li><a href="#"><i class="fas fa-map-marked-alt"></i> Missões Realizadas</a></li>
            <li><a href="#"><i class="fas fa-file-pdf"></i> Relatório em PDF</a></li>
            
            <?php if ($isAdmin): ?>
            <li class="has-submenu">
                <a href="#" id="admin-menu-toggle"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu open">
                    <li><a href="cadastro_aeronaves.php"> Cadastro de Aeronaves</a></li>
                    <li><a href="cadastro_pilotos.php" class="active"> Cadastro de Pilotos</a></li>
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
        <h1>Cadastro de Pilotos</h1>

        <?php echo $mensagem_status; ?>

        <div class="cadastro-form-container">
            <form id="pilotoForm" action="cadastro_pilotos.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_completo">Nome Completo:</label>
                        <input type="text" id="nome_completo" name="nome_completo" placeholder="Nome completo do piloto" required>
                    </div>
                    <div class="form-group">
                        <label for="rg">RG:</label>
                        <input type="text" id="rg" name="rg" placeholder="Ex: X.XXX.XXX-X" required>
                    </div>

                    <div class="form-group">
                        <label for="cpf">CPF:</label>
                        <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Formato: 000.000.000-00" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail:</label>
                        <input type="email" id="email" name="email" placeholder="email@exemplo.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="tel" id="telefone" name="telefone" placeholder="(XX) X XXXX-XXXX" pattern="\(\d{2}\) \d{1} \d{4}-\d{4}" title="Formato: (XX) X XXXX-XXXX" required>
                    </div>

                    <div class="form-group">
                        <label for="crbm_piloto">CRBM:</label>
                        <select id="crbm_piloto" name="crbm_piloto" required>
                            <option value="">Selecione o CRBM</option>
                            <option value="CCB">CCB</option>
                            <option value="BOA">BOA</option>
                            <option value="GOST">GOST</option>
                            <option value="1CRBM">1º CRBM</option>
                            <option value="2CRBM">2º CRBM</option>
                            <option value="3CRBM">3º CRBM</option>
                            <option value="4CRBM">4º CRBM</option>
                            <option value="5CRBM">5º CRBM</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="obm_piloto">OBM/Seção:</label>
                        <select id="obm_piloto" name="obm_piloto" required disabled>
                            <option value="">Selecione o CRBM Primeiro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cadastro_sarpas">Cadastro SARPAS:</label>
                        <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" placeholder="Ex: AB2025123456" required>
                    </div>

                    <div class="form-group">
                        <label for="cparp">CPARP:</label>
                        <select id="cparp" name="cparp" required>
                            <option value="">Selecione</option>
                            <option value="SIM">SIM</option>
                            <option value="NAO">NÃO</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha Inicial:</label>
                        <input type="password" id="senha" name="senha" placeholder="Crie uma senha provisória" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_piloto">Status:</label>
                        <select id="status_piloto" name="status_piloto" required>
                            <option value="ativo">Ativo</option>
                            <option value="afastado">Afastado</option>
                            <option value="desativado">Desativado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tipo_usuario">Tipo de Usuário:</label>
                        <select id="tipo_usuario" name="tipo_usuario" required>
                            <option value="">Selecione o Tipo</option>
                            <option value="administrador">Administrador</option>
                            <option value="piloto">Piloto</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="info_adicionais_piloto">Informações Adicionais (opcional):</label>
                        <textarea id="info_adicionais_piloto" name="info_adicionais_piloto" rows="4" placeholder="Adicione qualquer informação relevante sobre o piloto, como cursos, especializações, etc."></textarea>
                    </div>

                </div>
                <div class="form-actions">
                    <button type="submit" id="saveButton" disabled>Salvar Piloto</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const adminMenuToggle = document.getElementById('admin-menu-toggle');
        const adminSubmenu = document.querySelector('.has-submenu .submenu');

        if (adminMenuToggle && adminSubmenu) {
            if (adminSubmenu.querySelector('a.active')) {
                adminSubmenu.classList.add('open');
                const arrow = adminMenuToggle.querySelector('.submenu-arrow');
                if(arrow) arrow.classList.replace('fa-chevron-down', 'fa-chevron-up');
            }
            adminMenuToggle.addEventListener('click', function(event) {
                event.preventDefault();
                adminSubmenu.classList.toggle('open');
                const arrow = this.querySelector('.submenu-arrow');
                if (arrow) arrow.classList.toggle('fa-chevron-up');
            });
        }
        
        const obmPorCrbm = {
            'CCB': ['BM-1', 'BM-2', 'BM-3', 'BM-4', 'BM-5', 'BM-6', 'BM-7', 'BM-8'],
            'BOA': ['SOARP'],
            'GOST': ['GOST'],
            '1CRBM': ['1º BBM', '6º BBM', '7º BBM', '8º BBM'],
            '2CRBM': ['3º BBM', '11º BBM', '1ª CIBM'],
            '3CRBM': ['4º BBM', '9º BBM', '10º BBM', '13º BBM'],
            '4CRBM': ['5º BBM', '2ª CIBM', '4ª CIBM', '5ª CIBM'],
            '5CRBM': ['2º BBM', '12º BBM', '6ª CIBM']
        };

        const form = document.getElementById('pilotoForm');
        const saveButton = document.getElementById('saveButton');
        const requiredFields = Array.from(form.querySelectorAll('[required]'));
        const crbmSelect = document.getElementById('crbm_piloto');
        const obmSelect = document.getElementById('obm_piloto');

        function checkFormValidity() {
            const allValid = requiredFields.every(field => {
                if (field.disabled) return true;
                return field.value.trim() !== '';
            });
            saveButton.disabled = !allValid;
        }

        requiredFields.forEach(field => {
            field.addEventListener('input', checkFormValidity);
            field.addEventListener('change', checkFormValidity);
        });

        crbmSelect.addEventListener('change', function() {
            const crbm = this.value;
            obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';

            if (crbm && obmPorCrbm[crbm]) {
                obmSelect.disabled = false;
                obmPorCrbm[crbm].forEach(function(obm) {
                    const option = document.createElement('option');
                    option.value = obm;
                    option.textContent = obm;
                    obmSelect.appendChild(option);
                });
            } else {
                obmSelect.disabled = true;
            }
            checkFormValidity();
        });
        
        checkFormValidity();
    });
    </script>
</body>
</html>