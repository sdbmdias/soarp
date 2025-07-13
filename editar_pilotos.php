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
$piloto_data = null;
$piloto_id = null;

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $piloto_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT id, nome_completo, rg, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, tipo_usuario FROM pilotos WHERE id = ?");
    $stmt->bind_param("i", $piloto_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $piloto_data = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div id='status-message' class='error-message-box'>Piloto não encontrado.</div>";
    }
    $stmt->close();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['piloto_id'])) {
    $piloto_id = intval($_POST['piloto_id']);
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
    $tipo_usuario = htmlspecialchars($_POST['tipo_usuario']);

    $stmt = $conn->prepare("UPDATE pilotos SET nome_completo=?, rg=?, cpf=?, email=?, telefone=?, crbm_piloto=?, obm_piloto=?, cadastro_sarpas=?, cparp=?, status_piloto=?, info_adicionais=?, tipo_usuario=? WHERE id = ?");
    $stmt->bind_param("ssssssssssssi", $nome_completo, $rg, $cpf, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $tipo_usuario, $piloto_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div id='status-message' class='success-message-box'>Piloto atualizado com sucesso!</div>";
    } else {
        $mensagem_status = "<div id='status-message' class='error-message-box'>Erro ao atualizar piloto: " . $stmt->error . "</div>";
    }
    $stmt->close();
    
    // Recarrega os dados para exibir no formulário
    $stmt_reload = $conn->prepare("SELECT id, nome_completo, rg, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, tipo_usuario FROM pilotos WHERE id = ?");
    $stmt_reload->bind_param("i", $piloto_id);
    $stmt_reload->execute();
    $piloto_data = $stmt_reload->get_result()->fetch_assoc();
    $stmt_reload->close();
        
} else {
     if ($_SERVER["REQUEST_METHOD"] !== "POST" && !isset($_GET['id'])) {
        $mensagem_status = "<div id='status-message' class='error-message-box'>ID do piloto não fornecido para edição.</div>";
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Editar Piloto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;min-height:100vh;background-color:#f0f2f5;color:#333}.sidebar{width:250px;background-color:#2c3e50;color:#fff;padding-top:20px;box-shadow:2px 0 5px rgba(0,0,0,.1);display:flex;flex-direction:column;align-items:center}.sidebar ul{list-style:none;padding:0;width:100%}.sidebar ul li a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;transition:background-color .3s ease;font-size:16px;line-height:1.2}.sidebar ul li a i{margin-right:15px;font-size:20px;width:25px;text-align:center}.sidebar ul li a:hover,.sidebar ul li a.active{background-color:#34495e;border-left:5px solid #3498db;padding-left:15px}.menu-alert{color:#e74c3c!important;font-weight:700}.sidebar ul li.has-submenu>a{position:relative}.sidebar ul li.has-submenu>a .submenu-arrow{position:absolute;right:20px;transition:transform .3s ease}.sidebar ul li.has-submenu>a .submenu-arrow.fa-chevron-up{transform:rotate(180deg)}.sidebar ul li .submenu{list-style:none;padding:0;max-height:0;overflow:hidden;transition:max-height .3s ease-out;background-color:#34495e}.sidebar ul li .submenu li a{padding:10px 20px 10px 45px;font-size:.95em;background-color:transparent}.sidebar ul li .submenu li a:hover,.sidebar ul li .submenu li a.active{background-color:#3f5872;border-left:5px solid #3498db}.sidebar ul li .submenu.open{max-height:250px}.main-content{flex-grow:1;padding:30px;background-color:#f0f2f5;color:#333}.main-content h1{color:#2c3e50;margin-bottom:10px}.success-message-box,.error-message-box{padding:15px;border-radius:5px;text-align:center;font-weight:700;margin-bottom:20px;width:50%;margin-left:auto;margin-right:auto;box-shadow:0 2px 5px rgba(0,0,0,.1);opacity:1;transition:opacity .5s ease-out}.success-message-box{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}.error-message-box{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.edit-form-container{background-color:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);max-width:800px;margin:auto}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.form-group{margin-bottom:15px}.form-group label{display:block;margin-bottom:8px;font-weight:700;color:#555}.form-group input,.form-group select,.form-group textarea{width:calc(100% - 22px);padding:10px;border:1px solid #ccc;border-radius:5px;font-size:16px;color:#333}.form-group textarea{resize:vertical;min-height:80px}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#3498db;outline:0;box-shadow:0 0 5px rgba(52,152,219,.5)}.form-actions{grid-column:1/-1;text-align:right;padding-top:20px}.form-actions button{background-color:#007bff;color:#fff;padding:12px 25px;border:none;border-radius:5px;cursor:pointer;font-size:17px;transition:background-color .3s ease}.form-actions button:hover{background-color:#0056b3}.form-actions button:disabled{background-color:#6c757d;cursor:not-allowed}
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
                    <li><a href="cadastro_pilotos.php"> Cadastro de Pilotos</a></li>
                    <li><a href="cadastro_controles.php"> Cadastro de Controles</a></li>
                    <li><a href="alertas.php" class="<?php if ($existem_alertas) echo 'menu-alert'; ?>"> Alertas</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="index.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Editar Piloto</h1>

        <?php echo $mensagem_status; ?>

        <?php if ($piloto_data): ?>
        <div class="edit-form-container">
            <form id="editPilotoForm" action="editar_pilotos.php?id=<?php echo $piloto_id; ?>" method="POST">
                <input type="hidden" name="piloto_id" value="<?php echo htmlspecialchars($piloto_data['id']); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_completo">Nome Completo:</label>
                        <input type="text" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($piloto_data['nome_completo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="rg">RG:</label>
                        <input type="text" id="rg" name="rg" value="<?php echo htmlspecialchars($piloto_data['rg']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cpf">CPF:</label>
                        <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($piloto_data['cpf']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($piloto_data['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($piloto_data['telefone']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="crbm_piloto">CRBM:</label>
                        <select id="crbm_piloto" name="crbm_piloto" required>
                            <option value="">Selecione o CRBM</option>
                            <option value="CCB" <?php echo ($piloto_data['crbm_piloto'] == 'CCB') ? 'selected' : ''; ?>>CCB</option>
                            <option value="BOA" <?php echo ($piloto_data['crbm_piloto'] == 'BOA') ? 'selected' : ''; ?>>BOA</option>
                            <option value="GOST" <?php echo ($piloto_data['crbm_piloto'] == 'GOST') ? 'selected' : ''; ?>>GOST</option>
                            <option value="1CRBM" <?php echo ($piloto_data['crbm_piloto'] == '1CRBM') ? 'selected' : ''; ?>>1º CRBM</option>
                            <option value="2CRBM" <?php echo ($piloto_data['crbm_piloto'] == '2CRBM') ? 'selected' : ''; ?>>2º CRBM</option>
                            <option value="3CRBM" <?php echo ($piloto_data['crbm_piloto'] == '3CRBM') ? 'selected' : ''; ?>>3º CRBM</option>
                            <option value="4CRBM" <?php echo ($piloto_data['crbm_piloto'] == '4CRBM') ? 'selected' : ''; ?>>4º CRBM</option>
                            <option value="5CRBM" <?php echo ($piloto_data['crbm_piloto'] == '5CRBM') ? 'selected' : ''; ?>>5º CRBM</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="obm_piloto">OBM/Seção:</label>
                        <select id="obm_piloto" name="obm_piloto" required>
                             </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cadastro_sarpas">Cadastro SARPAS:</label>
                        <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" value="<?php echo htmlspecialchars($piloto_data['cadastro_sarpas']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cparp">CPARP:</label>
                        <select id="cparp" name="cparp" required>
                            <option value="">Selecione</option>
                            <option value="SIM" <?php echo ($piloto_data['cparp'] == 'SIM') ? 'selected' : ''; ?>>SIM</option>
                            <option value="NAO" <?php echo ($piloto_data['cparp'] == 'NAO') ? 'selected' : ''; ?>>NÃO</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status_piloto">Status:</label>
                        <select id="status_piloto" name="status_piloto" required>
                            <option value="ativo" <?php echo ($piloto_data['status_piloto'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="afastado" <?php echo ($piloto_data['status_piloto'] == 'afastado') ? 'selected' : ''; ?>>Afastado</option>
                            <option value="desativado" <?php echo ($piloto_data['status_piloto'] == 'desativado') ? 'selected' : ''; ?>>Desativado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tipo_usuario">Tipo de Usuário:</label>
                        <select id="tipo_usuario" name="tipo_usuario" required>
                            <option value="">Selecione o Tipo</option>
                            <option value="administrador" <?php echo ($piloto_data['tipo_usuario'] == 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="piloto" <?php echo ($piloto_data['tipo_usuario'] == 'piloto') ? 'selected' : ''; ?>>Piloto</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="info_adicionais_piloto">Informações Adicionais (opcional):</label>
                        <textarea id="info_adicionais_piloto" name="info_adicionais_piloto" rows="4"><?php echo htmlspecialchars($piloto_data['info_adicionais'] ?? ''); ?></textarea>
                    </div>

                </div>
                <div class="form-actions">
                    <button id="updateButton" type="submit">Atualizar Piloto</button>
                </div>
            </form>
        </div>
        <?php else: ?>
            <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados do piloto. Verifique se o ID está correto.</p>
        <?php endif; ?>
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

        const form = document.getElementById('editPilotoForm');
        const updateButton = document.getElementById('updateButton');
        const requiredFields = Array.from(form.querySelectorAll('[required]'));
        const crbmSelect = document.getElementById('crbm_piloto');
        const obmSelect = document.getElementById('obm_piloto');
        const valorSalvoOBM = "<?php echo addslashes($piloto_data['obm_piloto'] ?? ''); ?>";

        function checkFormValidity() {
            const allValid = requiredFields.every(field => field.value.trim() !== '');
            updateButton.disabled = !allValid;
        }

        function atualizarOBMs() {
            const crbm = crbmSelect.value;
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
            
            if(valorSalvoOBM) {
                obmSelect.value = valorSalvoOBM;
            }
        }

        requiredFields.forEach(field => {
            field.addEventListener('input', checkFormValidity);
            field.addEventListener('change', checkFormValidity);
        });
        
        crbmSelect.addEventListener('change', atualizarOBMs);
        
        atualizarOBMs();
        checkFormValidity();
    });
    </script>
</body>
</html>