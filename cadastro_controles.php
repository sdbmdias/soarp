<?php
session_start(); // Inicia a sessão

// Bloco de proteção para forçar redefinição de senha
if (isset($_SESSION['force_password_reset'])) {
    header('Location: primeiro_acesso.php');
    exit();
}

// Verifica se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'administrador') {
    header("Location: index.php");
    exit();
}

$isAdmin = true;

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

// --- Buscar Aeronaves para o Dropdown ---
$aeronaves_disponiveis = [];
$sql_aeronaves = "SELECT id, prefixo, modelo, crbm, obm FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);
if ($result_aeronaves->num_rows > 0) {
    while($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}
// --- Fim da busca de Aeronaves ---


$mensagem_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta dados fixos
    $fabricante = htmlspecialchars($_POST['fabricante']);
    $modelo = htmlspecialchars($_POST['modelo']);
    $numero_serie = htmlspecialchars($_POST['numero_serie']);
    $aeronave_id = !empty($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : NULL;
    $status = htmlspecialchars($_POST['status']);
    $data_aquisicao = htmlspecialchars($_POST['data_aquisicao']);
    $info_adicionais = htmlspecialchars($_POST['info_adicionais']);

    $crbm = '';
    $obm = '';

    // LÓGICA DE LOTAÇÃO CORRIGIDA E MAIS SEGURA
    if ($aeronave_id) {
        // Se um drone foi vinculado, busca a lotação correta do banco de dados para garantir a integridade.
        $stmt_get_acft_data = $conn->prepare("SELECT crbm, obm FROM aeronaves WHERE id = ?");
        $stmt_get_acft_data->bind_param("i", $aeronave_id);
        $stmt_get_acft_data->execute();
        $result_acft_data = $stmt_get_acft_data->get_result();
        if ($result_acft_data->num_rows > 0) {
            $acft_data = $result_acft_data->fetch_assoc();
            $crbm = $acft_data['crbm'];
            $obm = $acft_data['obm'];
        }
        $stmt_get_acft_data->close();
    } else {
        // Se for um controle reserva, pega a lotação do formulário.
        $crbm = htmlspecialchars($_POST['crbm']);
        $obm = htmlspecialchars($_POST['obm']);
    }

    // Prepara a query SQL para inserir os dados na tabela 'controles'
    $stmt = $conn->prepare("INSERT INTO controles (fabricante, modelo, numero_serie, aeronave_id, crbm, obm, status, data_aquisicao, info_adicionais) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisssss", $fabricante, $modelo, $numero_serie, $aeronave_id, $crbm, $obm, $status, $data_aquisicao, $info_adicionais);

    if ($stmt->execute()) {
        $mensagem_status = "<div id='status-message' class='success-message-box'>Controle cadastrado e vinculado com sucesso!</div>";
    } else {
        if ($conn->errno == 1062) {
             $mensagem_status = "<div id='status-message' class='error-message-box'>Erro: O número de série informado ('" . htmlspecialchars($numero_serie) . "') já está cadastrado.</div>";
        } else {
            $mensagem_status = "<div id='status-message' class='error-message-box'>Erro ao cadastrar controle: " . $stmt->error . "</div>";
        }
    }

    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Cadastro de Controles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;min-height:100vh;background-color:#f0f2f5;color:#333}.sidebar{width:250px;background-color:#2c3e50;color:#fff;padding-top:20px;box-shadow:2px 0 5px rgba(0,0,0,.1);display:flex;flex-direction:column;align-items:center}.sidebar ul{list-style:none;padding:0;width:100%}.sidebar ul li a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;transition:background-color .3s ease;font-size:16px;line-height:1.2}.sidebar ul li a i{margin-right:15px;font-size:20px;width:25px;text-align:center}.sidebar ul li a:hover,.sidebar ul li a.active{background-color:#34495e;border-left:5px solid #3498db;padding-left:15px}.menu-alert{color:#e74c3c!important;font-weight:700}.sidebar ul li.has-submenu>a{position:relative}.sidebar ul li.has-submenu>a .submenu-arrow{position:absolute;right:20px;transition:transform .3s ease}.sidebar ul li.has-submenu>a .submenu-arrow.fa-chevron-up{transform:rotate(180deg)}.sidebar ul li .submenu{list-style:none;padding:0;max-height:0;overflow:hidden;transition:max-height .3s ease-out;background-color:#34495e}.sidebar ul li .submenu li a{padding:10px 20px 10px 45px;font-size:.95em;background-color:transparent}.sidebar ul li .submenu li a:hover,.sidebar ul li .submenu li a.active{background-color:#3f5872;border-left:5px solid #3498db}.sidebar ul li .submenu.open{max-height:250px}.main-content{flex-grow:1;padding:30px;background-color:#f0f2f5;color:#333}.main-content h1{color:#2c3e50;margin-bottom:10px}.success-message-box,.error-message-box{padding:15px;border-radius:5px;text-align:center;font-weight:700;margin-bottom:20px;width:50%;margin-left:auto;margin-right:auto;box-shadow:0 2px 5px rgba(0,0,0,.1);opacity:1;transition:opacity .5s ease-out 3s}.success-message-box{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}.error-message-box{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.cadastro-form-container{background-color:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);max-width:800px;margin:auto}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.form-group{margin-bottom:15px}.form-group label{display:block;margin-bottom:8px;font-weight:700;color:#555}.form-group input,.form-group select,.form-group textarea{width:calc(100% - 22px);padding:10px;border:1px solid #ccc;border-radius:5px;font-size:16px;color:#333}.form-group select:disabled{background-color:#e9ecef}.form-group textarea{resize:vertical;min-height:80px}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#3498db;outline:0;box-shadow:0 0 5px rgba(52,152,219,.5)}.form-actions{grid-column:1/-1;text-align:right;padding-top:20px}.form-actions button{background-color:#28a745;color:#fff;padding:12px 25px;border:none;border-radius:5px;cursor:pointer;font-size:17px;transition:background-color .3s ease}.form-actions button:hover{background-color:#218838}.form-actions button:disabled{background-color:#a5d6a7;cursor:not-allowed}
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
                    <li><a href="cadastro_controles.php" class="active"> Cadastro de Controles</a></li>
                    <li><a href="alertas.php" class="<?php if ($existem_alertas) echo 'menu-alert'; ?>"> Alertas</a></li> 
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="index.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Cadastro de Controles (Rádios)</h1>

        <?php echo $mensagem_status; ?>

        <div class="cadastro-form-container">
            <form id="controleForm" action="cadastro_controles.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fabricante">Fabricante:</label>
                        <select id="fabricante" name="fabricante" required>
                            <option value="">Selecione o Fabricante</option>
                            <option value="DJI">DJI</option>
                            <option value="Autel Robotics">Autel Robotics</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modelo">Modelo:</label>
                        <select id="modelo" name="modelo" required disabled>
                            <option value="">Selecione o Fabricante Primeiro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="numero_serie">Número de Série:</label>
                        <input type="text" id="numero_serie" name="numero_serie" placeholder="Número de série do controle" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="aeronave_id">Vincular à Aeronave (Prefixo):</label>
                        <select id="aeronave_id" name="aeronave_id">
                            <option value="">Nenhuma (Controle Reserva)</option>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($aeronave['id'] ?? ''); ?>"
                                    data-crbm="<?php echo htmlspecialchars($aeronave['crbm'] ?? ''); ?>"
                                    data-obm="<?php echo htmlspecialchars($aeronave['obm'] ?? ''); ?>"
                                >
                                    <?php echo htmlspecialchars(($aeronave['prefixo'] ?? 'N/A') . ' - ' . ($aeronave['modelo'] ?? 'N/A')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="crbm">CRBM de Lotação:</label>
                        <select id="crbm" name="crbm" required>
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
                        <label for="obm">OBM/Seção de Lotação:</label>
                        <select id="obm" name="obm" required disabled>
                            <option value="">Selecione o CRBM Primeiro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="ativo">Ativo</option>
                            <option value="em_manutencao">Em Manutenção</option>
                            <option value="baixado">Baixado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="data_aquisicao">Data de Aquisição:</label>
                        <input type="date" id="data_aquisicao" name="data_aquisicao" required>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="info_adicionais">Informações Adicionais (opcional):</label>
                        <textarea id="info_adicionais" name="info_adicionais" rows="4" placeholder="Adicione qualquer informação relevante sobre o controle."></textarea>
                    </div>

                </div>
                <div class="form-actions">
                    <button id="saveButton" type="submit" disabled>Salvar Controle</button>
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
                if(arrow) arrow.classList.toggle('fa-chevron-up');
            });
        }
        
        const modelosControlePorFabricante = {
            'DJI': ['RC', 'RC 2', 'RC Pro', 'RC Plus', 'Smart Controller'],
            'Autel Robotics': ['Smart Controller V3', 'Smart Controller SE']
        };
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

        const form = document.getElementById('controleForm');
        const saveButton = document.getElementById('saveButton');
        const requiredFields = Array.from(form.querySelectorAll('[required]'));

        const fabricanteSelect = document.getElementById('fabricante');
        const modeloSelect = document.getElementById('modelo');
        const crbmSelect = document.getElementById('crbm');
        const obmSelect = document.getElementById('obm');
        const aeronaveSelect = document.getElementById('aeronave_id');
        
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

        fabricanteSelect.addEventListener('change', function() {
            const fabricante = this.value;
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            modeloSelect.disabled = true;

            if (fabricante && modelosControlePorFabricante[fabricante]) {
                modeloSelect.disabled = false;
                modelosControlePorFabricante[fabricante].forEach(function(modelo) {
                    const option = document.createElement('option');
                    option.value = modelo;
                    option.textContent = modelo;
                    modeloSelect.appendChild(option);
                });
            }
            checkFormValidity();
        });

        crbmSelect.addEventListener('change', function() {
            const crbm = this.value;
            obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
            obmSelect.disabled = true;

            if (crbm && obmPorCrbm[crbm]) {
                obmSelect.disabled = false;
                obmPorCrbm[crbm].forEach(function(obm) {
                    const option = document.createElement('option');
                    option.value = obm;
                    option.textContent = obm;
                    obmSelect.appendChild(option);
                });
            }
            checkFormValidity();
        });

        aeronaveSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value) { 
                const crbm = selectedOption.getAttribute('data-crbm');
                const obm = selectedOption.getAttribute('data-obm');

                crbmSelect.value = crbm;
                crbmSelect.dispatchEvent(new Event('change'));
                
                // Pequeno atraso para garantir que o OBM foi populado antes de selecionar
                setTimeout(() => {
                    obmSelect.value = obm;
                }, 0);

                crbmSelect.disabled = true;
                obmSelect.disabled = true; 

            } else { 
                crbmSelect.disabled = false;
                obmSelect.disabled = true;
                crbmSelect.value = '';
                obmSelect.innerHTML = '<option value="">Selecione o CRBM Primeiro</option>';
            }
            checkFormValidity();
        });

        // **NOVO BLOCO DE CÓDIGO PARA A CORREÇÃO**
        form.addEventListener('submit', function() {
            // Reativa os campos de lotação antes do envio para que seus valores sejam incluídos no POST
            crbmSelect.disabled = false;
            obmSelect.disabled = false;
        });

        checkFormValidity();
    });
    </script>
</body>
</html>