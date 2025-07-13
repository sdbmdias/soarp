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
    // Se não for administrador, redireciona para o dashboard, pois esta página é restrita
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

// Busca prefixos já utilizados para desabilitá-los na lista de seleção
$usados_prefixos = [];
$conn_read_prefixes = new mysqli($servername, $username, $password, $dbname);
if ($conn_read_prefixes->connect_error) {
    error_log("Falha na conexão com o banco de dados para prefixos: " . $conn_read_prefixes->connect_error);
} else {
    $sql_used_prefixes = "SELECT prefixo FROM aeronaves";
    $result_used_prefixes = $conn_read_prefixes->query($sql_used_prefixes);
    if ($result_used_prefixes) {
        while ($row = $result_used_prefixes->fetch_assoc()) {
            $usados_prefixos[] = $row['prefixo'];
        }
    }
    $conn_read_prefixes->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fabricante = htmlspecialchars($_POST['fabricante']);
    $modelo = htmlspecialchars($_POST['modelo']);
    $prefixo = htmlspecialchars($_POST['prefixo']);
    $numero_serie = htmlspecialchars($_POST['numero_serie']);
    $cadastro_sisant = htmlspecialchars($_POST['cadastro_sisant']);
    $validade_sisant = htmlspecialchars($_POST['validade_sisant']);
    $crbm = htmlspecialchars($_POST['crbm']);
    $obm = htmlspecialchars($_POST['obm']);
    $tipo_drone = htmlspecialchars($_POST['tipo_drone']);
    $pmd_kg = floatval($_POST['pmd_kg']);
    $data_aquisicao = htmlspecialchars($_POST['data_aquisicao']);
    $status = htmlspecialchars($_POST['status']);
    $info_adicionais = htmlspecialchars($_POST['info_adicionais']);

    $conn_insert = new mysqli($servername, $username, $password, $dbname);

    if ($conn_insert->connect_error) {
        die("Falha na conexão com o banco de dados para inserção: " . $conn_insert->connect_error);
    }

    $stmt = $conn_insert->prepare("INSERT INTO aeronaves (fabricante, modelo, prefixo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, data_aquisicao, status, info_adicionais) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sssssssssdsss", $fabricante, $modelo, $prefixo, $numero_serie, $cadastro_sisant, $validade_sisant, $crbm, $obm, $tipo_drone, $pmd_kg, $data_aquisicao, $status, $info_adicionais);

    if ($stmt->execute()) {
        $mensagem_status = "<div id='status-message' class='success-message-box'>Aeronave cadastrada com sucesso!</div>";
        // Limpa a lista de prefixos usados para forçar a atualização
        $usados_prefixos[] = $prefixo;
    } else {
        $mensagem_status = "<div id='status-message' class='error-message-box'>Erro ao cadastrar aeronave: " . $stmt->error . "</div>";
    }

    $stmt->close();
    $conn_insert->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Cadastro de Aeronaves</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;min-height:100vh;background-color:#f0f2f5;color:#333}.sidebar{width:250px;background-color:#2c3e50;color:#fff;padding-top:20px;box-shadow:2px 0 5px rgba(0,0,0,.1);display:flex;flex-direction:column;align-items:center}.sidebar ul{list-style:none;padding:0;width:100%}.sidebar ul li a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;transition:background-color .3s ease;font-size:16px;line-height:1.2}.sidebar ul li a i{margin-right:15px;font-size:20px;width:25px;text-align:center}.sidebar ul li a:hover,.sidebar ul li a.active{background-color:#34495e;border-left:5px solid #3498db;padding-left:15px}.menu-alert{color:#e74c3c!important;font-weight:700}.sidebar ul li.has-submenu>a{position:relative}.sidebar ul li.has-submenu>a .submenu-arrow{position:absolute;right:20px;transition:transform .3s ease}.sidebar ul li.has-submenu>a .submenu-arrow.fa-chevron-up{transform:rotate(180deg)}.sidebar ul li .submenu{list-style:none;padding:0;max-height:0;overflow:hidden;transition:max-height .3s ease-out;background-color:#34495e}.sidebar ul li .submenu li a{padding:10px 20px 10px 45px;font-size:.95em;background-color:transparent}.sidebar ul li .submenu li a:hover,.sidebar ul li .submenu li a.active{background-color:#3f5872;border-left:5px solid #3498db}.sidebar ul li .submenu.open{max-height:250px}.main-content{flex-grow:1;padding:30px;background-color:#f0f2f5;color:#333}.main-content h1{color:#2c3e50;margin-bottom:10px}.success-message-box,.error-message-box{padding:15px;border-radius:5px;text-align:center;font-weight:700;margin-bottom:20px;width:50%;margin-left:auto;margin-right:auto;box-shadow:0 2px 5px rgba(0,0,0,.1);opacity:1;transition:opacity .5s ease-out 3s}.success-message-box{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}.error-message-box{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.cadastro-form-container{background-color:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);max-width:800px;margin:auto}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.form-group{margin-bottom:15px}.form-group label{display:block;margin-bottom:8px;font-weight:700;color:#555}.form-group input,.form-group select,.form-group textarea{width:calc(100% - 22px);padding:10px;border:1px solid #ccc;border-radius:5px;font-size:16px;color:#333}.form-group textarea{resize:vertical;min-height:80px}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#3498db;outline:0;box-shadow:0 0 5px rgba(52,152,219,.5)}.form-actions{grid-column:1/-1;text-align:right;padding-top:20px}.form-actions button{background-color:#28a745;color:#fff;padding:12px 25px;border:none;border-radius:5px;cursor:pointer;font-size:17px;transition:background-color .3s ease}.form-actions button:hover{background-color:#218838}.form-actions button:disabled{background-color:#a5d6a7;cursor:not-allowed}
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
                    <li><a href="cadastro_aeronaves.php" class="active"> Cadastro de Aeronaves</a></li>
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
        <h1>Cadastro de Aeronaves</h1>

        <?php echo $mensagem_status; ?>

        <div class="cadastro-form-container">
            <form id="aeronaveForm" action="cadastro_aeronaves.php" method="POST">
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
                        <label for="prefixo">Prefixo:</label>
                        <select id="prefixo" name="prefixo" required>
                            <option value="">Selecione o Prefixo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="numero_serie">Número de Série:</label>
                        <input type="text" id="numero_serie" name="numero_serie" placeholder="Ex: 1587ABC987DEF" required>
                    </div>
                    <div class="form-group">
                        <label for="cadastro_sisant">Cadastro SISANT:</label>
                        <input type="text" id="cadastro_sisant" name="cadastro_sisant" placeholder="Ex: PP-2025193001" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="validade_sisant">Validade SISANT:</label>
                        <input type="date" id="validade_sisant" name="validade_sisant" required>
                    </div>

                    <div class="form-group">
                        <label for="crbm">CRBM:</label>
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
                        <label for="obm">OBM/Seção:</label>
                        <select id="obm" name="obm" required disabled>
                            <option value="">Selecione o CRBM Primeiro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo_drone">Tipo de Drone:</label>
                        <select id="tipo_drone" name="tipo_drone" required>
                            <option value="">Selecione o Tipo</option>
                            <option value="multi_rotor">Multi-rotor</option>
                            <option value="asa_fixa">Asa Fixa</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pmd_kg">PMD (kg):</label>
                        <input type="number" id="pmd_kg" name="pmd_kg" step="0.01" placeholder="Ex: 0.9 (kg)" required>
                    </div>
                    <div class="form-group">
                        <label for="data_aquisicao">Data de Aquisição:</label>
                        <input type="date" id="data_aquisicao" name="data_aquisicao" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="ativo">Ativa</option>
                            <option value="em_manutencao">Em Manutenção</option>
                            <option value="baixada">Baixada</option>
                            <option value="adida">Adida</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="info_adicionais">Informações Adicionais (opcional):</label> <textarea id="info_adicionais" name="info_adicionais" rows="4" placeholder="Adicione qualquer informação relevante sobre a aeronave."></textarea>
                    </div>

                </div>
                <div class="form-actions">
                    <button id="saveButton" type="submit" disabled>Salvar Aeronave</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const e = {
            DJI: ["DJI FlyCart 30", "DJI FlyCart 100", "DJI Mini 3 Pro", "DJI Mini 4 Pro", "Matrice 30 Thermal (M30T)", "Matrice 300 RTK", "Matrice 350 RTK", "Mavic 2 Enterprise", "Mavic 2 Enterprise Advanced", "Mavic 3 Classic", "Mavic 3 Enterprise (M3E)", "Mavic 3 Multispectral (M3M)", "Mavic 3 Pro", "Mavic 3 Thermal (M3T)", "Phantom 3", "Phantom 4 Pro V2.0", "Phantom 4 RTK"],
            "Autel Robotics": ["Dragonfish Lite", "Dragonfish Pro", "Dragonfish Standard", "EVO II Dual 640T (V1/V2)", "EVO II Dual 640T V3", "EVO II Enterprise V3", "EVO II Pro (V1/V2)", "EVO II Pro V3", "EVO Lite+", "EVO MAX 4N", "EVO MAX 4T", "EVO Nano+"]
        }, o = {
            CCB: ["BM-1", "BM-2", "BM-3", "BM-4", "BM-5", "BM-6", "BM-7", "BM-8"],
            BOA: ["SOARP"],
            GOST: ["GOST"],
            "1CRBM": ["1º BBM", "6º BBM", "7º BBM", "8º BBM"],
            "2CRBM": ["3º BBM", "11º BBM", "1ª CIBM"],
            "3CRBM": ["4º BBM", "9º BBM", "10º BBM", "13º BBM"],
            "4CRBM": ["5º BBM", "2ª CIBM", "4ª CIBM", "5ª CIBM"],
            "5CRBM": ["2º BBM", "12º BBM", "6ª CIBM"]
        };
        for (const t in e) e[t].sort((e, o) => e.localeCompare(o, void 0, { numeric: !0, sensitivity: "base" }));

        const form = document.getElementById('aeronaveForm');
        const saveButton = document.getElementById('saveButton');
        const requiredFields = Array.from(form.querySelectorAll('[required]'));

        const t = document.getElementById("fabricante"),
            n = document.getElementById("modelo"),
            a = document.getElementById("crbm"),
            i = document.getElementById("obm"),
            l = document.getElementById("prefixo");

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
        
        t.addEventListener("change", function() {
            const o = this.value;
            n.innerHTML = '<option value="">Selecione o Modelo</option>';
            if (e[o]) {
                n.disabled = !1;
                e[o].forEach(function(e) { const o = document.createElement("option"); o.value = e, o.textContent = e, n.appendChild(o) })
            } else {
                n.disabled = !0
            }
            checkFormValidity();
        });

        a.addEventListener("change", function() {
            const e = this.value;
            i.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
            if (o[e]) {
                i.disabled = !1;
                o[e].forEach(function(e) { const o = document.createElement("option"); o.value = e, o.textContent = e, i.appendChild(o) })
            } else {
                i.disabled = !0
            }
            checkFormValidity();
        });

        const s = <?php echo json_encode($usados_prefixos); ?>;
        for (let c = 1; c <= 50; c++) {
            const r = `HAWK ${c.toString().padStart(2,"0")}`,
                d = document.createElement("option");
            d.value = r, d.textContent = r, s.includes(r) && (d.disabled = !0, d.textContent += " (em uso)"), l.appendChild(d)
        }

        const m = document.getElementById("admin-menu-toggle"),
            u = document.querySelector(".has-submenu .submenu");
        m && u && (u.querySelector("a.active") && u.classList.add("open"), m.addEventListener("click", function(e) {
            e.preventDefault(), u.classList.toggle("open");
            const o = this.querySelector(".submenu-arrow");
            o && o.classList.toggle("fa-chevron-down")
        }));
        
        checkFormValidity();
    });
    </script>
</body>
</html>