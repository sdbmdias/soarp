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

// Busca prefixos já em uso
$usados_prefixos = [];
$sql_used_prefixes = "SELECT prefixo FROM aeronaves";
$result_used_prefixes = $conn->query($sql_used_prefixes);
if ($result_used_prefixes) {
    while ($row = $result_used_prefixes->fetch_assoc()) {
        $usados_prefixos[] = $row['prefixo'];
    }
}

// Busca fabricantes e modelos do banco de dados
$fabricantes_e_modelos = [];
$sql_modelos = "SELECT fabricante, modelo FROM fabricantes_modelos WHERE tipo = 'Aeronave' ORDER BY CASE WHEN fabricante = 'DJI' THEN 1 WHEN fabricante = 'Autel Robotics' THEN 2 ELSE 3 END, fabricante, modelo";
$result_modelos = $conn->query($sql_modelos);
if ($result_modelos) {
    while ($row = $result_modelos->fetch_assoc()) {
        $fabricantes_e_modelos[$row['fabricante']][] = $row['modelo'];
    }
}

// Busca CRBMs e OBMs com a nova ordenação
$unidades = [];
$sql_unidades = "
    SELECT crbm, obm FROM crbm_obm 
    ORDER BY 
        CASE WHEN crbm NOT LIKE '%CRBM' THEN 1 ELSE 2 END, crbm, 
        CASE WHEN obm LIKE '%BBM%' THEN 1 WHEN obm LIKE '%CIBM%' THEN 2 ELSE 3 END, obm";
$result_unidades = $conn->query($sql_unidades);
if ($result_unidades) {
    while($row = $result_unidades->fetch_assoc()) {
        $unidades[$row['crbm']][] = $row['obm'];
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
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']);
    $info_adicionais = htmlspecialchars($_POST['info_adicionais']);

    $stmt = $conn->prepare("INSERT INTO aeronaves (fabricante, modelo, prefixo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, data_aquisicao, status, homologacao_anatel, info_adicionais) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssdssss", $fabricante, $modelo, $prefixo, $numero_serie, $cadastro_sisant, $validade_sisant, $crbm, $obm, $tipo_drone, $pmd_kg, $data_aquisicao, $status, $homologacao_anatel, $info_adicionais);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Aeronave cadastrada com sucesso!</div>";
        $usados_prefixos[] = $prefixo; // Atualiza a lista de prefixos em uso na mesma requisição
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
                        <?php foreach (array_keys($fabricantes_e_modelos) as $fab): ?>
                            <option value="<?php echo htmlspecialchars($fab); ?>"><?php echo htmlspecialchars($fab); ?></option>
                        <?php endforeach; ?>
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
                        <?php foreach (array_keys($unidades) as $crbm_unidade): ?>
                            <option value="<?php echo htmlspecialchars($crbm_unidade); ?>"><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm_unidade)); ?></option>
                        <?php endforeach; ?>
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
    // Carrega os modelos e unidades do PHP
    const modelosPorFabricante = <?php echo json_encode($fabricantes_e_modelos); ?>;
    const obmPorCrbm = <?php echo json_encode($unidades); ?>;

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
        modeloSelect.disabled = true;

        if (fabricante && modelosPorFabricante[fabricante]) {
            modeloSelect.disabled = false;
            modelosPorFabricante[fabricante].forEach(function(modelo) {
                const option = document.createElement("option");
                option.value = modelo;
                option.textContent = modelo;
                modeloSelect.appendChild(option);
            });
        }
        checkFormValidity();
    });

    crbmSelect.addEventListener("change", function() {
        const crbm = this.value;
        obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
        obmSelect.disabled = true;

        if (crbm && obmPorCrbm[crbm]) {
            obmSelect.disabled = false;
            obmPorCrbm[crbm].forEach(function(obm) {
                const option = document.createElement("option");
                option.value = obm;
                option.textContent = obm;
                obmSelect.appendChild(option);
            });
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