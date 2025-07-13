<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";

$usados_prefixos = [];
$sql_used_prefixes = "SELECT prefixo FROM aeronaves";
$result_used_prefixes = $conn->query($sql_used_prefixes);
if ($result_used_prefixes) {
    while ($row = $result_used_prefixes->fetch_assoc()) {
        $usados_prefixos[] = $row['prefixo'];
    }
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
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']); // NOVO CAMPO
    $info_adicionais = htmlspecialchars($_POST['info_adicionais']);

    $stmt = $conn->prepare("INSERT INTO aeronaves (fabricante, modelo, prefixo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, data_aquisicao, status, homologacao_anatel, info_adicionais) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssdssss", $fabricante, $modelo, $prefixo, $numero_serie, $cadastro_sisant, $validade_sisant, $crbm, $obm, $tipo_drone, $pmd_kg, $data_aquisicao, $status, $homologacao_anatel, $info_adicionais);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Aeronave cadastrada com sucesso!</div>";
        $usados_prefixos[] = $prefixo;
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar aeronave: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}
?>

<div class="main-content">
    <h1>Cadastro de Aeronaves</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
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
                 <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label> <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim">Sim</option>
                        <option value="Não" selected>Não</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4" placeholder="Adicione qualquer informação relevante sobre a aeronave."></textarea>
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
    const modelosPorFabricante = {
        'DJI': ["DJI FlyCart 30", "DJI FlyCart 100", "DJI Mini 3 Pro", "DJI Mini 4 Pro", "Matrice 30 Thermal (M30T)", "Matrice 300 RTK", "Matrice 350 RTK", "Mavic 2 Enterprise", "Mavic 2 Enterprise Advanced", "Mavic 3 Classic", "Mavic 3 Enterprise (M3E)", "Mavic 3 Multispectral (M3M)", "Mavic 3 Pro", "Mavic 3 Thermal (M3T)", "Phantom 3", "Phantom 4 Pro V2.0", "Phantom 4 RTK"],
        'Autel Robotics': ["Dragonfish Lite", "Dragonfish Pro", "Dragonfish Standard", "EVO II Dual 640T (V1/V2)", "EVO II Dual 640T V3", "EVO II Enterprise V3", "EVO II Pro (V1/V2)", "EVO II Pro V3", "EVO Lite+", "EVO MAX 4N", "EVO MAX 4T", "EVO Nano+"]
    };
    const obmPorCrbm = {
        'CCB': ["BM-1", "BM-2", "BM-3", "BM-4", "BM-5", "BM-6", "BM-7", "BM-8"], 'BOA': ["SOARP"], 'GOST': ["GOST"],
        '1CRBM': ["1º BBM", "6º BBM", "7º BBM", "8º BBM"], '2CRBM': ["3º BBM", "11º BBM", "1ª CIBM"],
        '3CRBM': ["4º BBM", "9º BBM", "10º BBM", "13º BBM"], '4CRBM': ["5º BBM", "2ª CIBM", "4ª CIBM", "5ª CIBM"],
        '5CRBM': ["2º BBM", "12º BBM", "6ª CIBM"]
    };

    for (const fab in modelosPorFabricante) {
        modelosPorFabricante[fab].sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
    }

    const form = document.getElementById('aeronaveForm');
    const saveButton = document.getElementById('saveButton');
    const requiredFields = Array.from(form.querySelectorAll('[required]'));

    const fabricanteSelect = document.getElementById("fabricante");
    const modeloSelect = document.getElementById("modelo");
    const crbmSelect = document.getElementById("crbm");
    const obmSelect = document.getElementById("obm");
    const prefixoSelect = document.getElementById("prefixo");

    function checkFormValidity() {
        const allValid = requiredFields.every(field => field.disabled ? true : field.value.trim() !== '');
        saveButton.disabled = !allValid;
    }

    requiredFields.forEach(field => {
        field.addEventListener('input', checkFormValidity);
        field.addEventListener('change', checkFormValidity);
    });
    
    fabricanteSelect.addEventListener("change", function() {
        const fabricante = this.value;
        modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
        if (modelosPorFabricante[fabricante]) {
            modeloSelect.disabled = false;
            modelosPorFabricante[fabricante].forEach(function(modelo) {
                const option = document.createElement("option");
                option.value = modelo;
                option.textContent = modelo;
                modeloSelect.appendChild(option);
            });
        } else {
            modeloSelect.disabled = true;
        }
        checkFormValidity();
    });

    crbmSelect.addEventListener("change", function() {
        const crbm = this.value;
        obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
        if (obmPorCrbm[crbm]) {
            obmSelect.disabled = false;
            obmPorCrbm[crbm].forEach(function(obm) {
                const option = document.createElement("option");
                option.value = obm;
                option.textContent = obm;
                obmSelect.appendChild(option);
            });
        } else {
            obmSelect.disabled = true;
        }
        checkFormValidity();
    });

    const prefixosUsados = <?php echo json_encode($usados_prefixos); ?>;
    for (let i = 1; i <= 50; i++) {
        const nomePrefixo = `HAWK ${i.toString().padStart(2, "0")}`;
        const option = document.createElement("option");
        option.value = nomePrefixo;
        option.textContent = nomePrefixo;
        if (prefixosUsados.includes(nomePrefixo)) {
            option.disabled = true;
            option.textContent += " (em uso)";
        }
        prefixoSelect.appendChild(option);
    }
    
    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>