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
$aeronave_data = null;

$aeronave_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : null);

// Busca prefixos em uso, excluindo o da aeronave atual
$usados_prefixos = [];
if ($aeronave_id) {
    $stmt_prefixes = $conn->prepare("SELECT prefixo FROM aeronaves WHERE id != ?");
    $stmt_prefixes->bind_param("i", $aeronave_id);
    $stmt_prefixes->execute();
    $result_used_prefixes = $stmt_prefixes->get_result();
    while ($row_prefix = $result_used_prefixes->fetch_assoc()) {
        $usados_prefixos[] = $row_prefix['prefixo'];
    }
    $stmt_prefixes->close();
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


// Lógica de atualização do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST" && $aeronave_id) {
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

    $stmt = $conn->prepare("UPDATE aeronaves SET fabricante=?, modelo=?, prefixo=?, numero_serie=?, cadastro_sisant=?, validade_sisant=?, crbm=?, obm=?, tipo_drone=?, pmd_kg=?, data_aquisicao=?, status=?, homologacao_anatel=?, info_adicionais=? WHERE id = ?");
    $stmt->bind_param("sssssssssdssssi", $fabricante, $modelo, $prefixo, $numero_serie, $cadastro_sisant, $validade_sisant, $crbm, $obm, $tipo_drone, $pmd_kg, $data_aquisicao, $status, $homologacao_anatel, $info_adicionais, $aeronave_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Aeronave atualizada com sucesso! Redirecionando...</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar aeronave: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}

// Carrega os dados da aeronave para preencher o formulário
if ($aeronave_id) {
    $stmt_load = $conn->prepare("SELECT * FROM aeronaves WHERE id = ?");
    $stmt_load->bind_param("i", $aeronave_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $aeronave_data = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div class='error-message-box'>Aeronave não encontrada.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID da aeronave não fornecido para edição.</div>";
    }
}
?>

<div class="main-content">
    <h1>Editar Aeronave</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($aeronave_data): ?>
    <div class="form-container">
        <form id="editAeronaveForm" action="editar_aeronaves.php?id=<?php echo htmlspecialchars($aeronave_id); ?>" method="POST">
            <input type="hidden" name="aeronave_id" value="<?php echo htmlspecialchars($aeronave_data['id']); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="">Selecione o Fabricante</option>
                        <?php foreach (array_keys($fabricantes_e_modelos) as $fab): ?>
                            <option value="<?php echo htmlspecialchars($fab); ?>" <?php echo ($aeronave_data['fabricante'] == $fab) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fab); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <select id="modelo" name="modelo" required> </select>
                </div>
                <div class="form-group">
                    <label for="prefixo">Prefixo:</label>
                    <select id="prefixo" name="prefixo" required> </select>
                </div>
                <div class="form-group">
                    <label for="numero_serie">Número de Série:</label>
                    <input type="text" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($aeronave_data['numero_serie']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cadastro_sisant">Cadastro SISANT:</label>
                    <input type="text" id="cadastro_sisant" name="cadastro_sisant" value="<?php echo htmlspecialchars($aeronave_data['cadastro_sisant']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="validade_sisant">Validade SISANT:</label>
                    <input type="date" id="validade_sisant" name="validade_sisant" value="<?php echo htmlspecialchars($aeronave_data['validade_sisant']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="crbm">CRBM:</label>
                    <select id="crbm" name="crbm" required>
                        <option value="">Selecione o CRBM</option>
                        <?php foreach (array_keys($unidades) as $crbm_unidade): ?>
                            <option value="<?php echo htmlspecialchars($crbm_unidade); ?>" <?php echo ($aeronave_data['crbm'] == $crbm_unidade) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm_unidade)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm">OBM/Seção:</label>
                    <select id="obm" name="obm" required> </select>
                </div>
                <div class="form-group">
                    <label for="tipo_drone">Tipo de Drone:</label>
                    <select id="tipo_drone" name="tipo_drone" required>
                        <option value="">Selecione o Tipo</option>
                        <option value="multi_rotor" <?php echo ($aeronave_data['tipo_drone'] == 'multi_rotor') ? 'selected' : ''; ?>>Multi-rotor</option>
                        <option value="asa_fixa" <?php echo ($aeronave_data['tipo_drone'] == 'asa_fixa') ? 'selected' : ''; ?>>Asa Fixa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pmd_kg">PMD (kg):</label>
                    <input type="number" id="pmd_kg" name="pmd_kg" step="0.01" value="<?php echo htmlspecialchars($aeronave_data['pmd_kg']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="data_aquisicao">Data de Aquisição:</label>
                    <input type="date" id="data_aquisicao" name="data_aquisicao" value="<?php echo htmlspecialchars($aeronave_data['data_aquisicao']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?php echo ($aeronave_data['status'] == 'ativo') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="em_manutencao" <?php echo ($aeronave_data['status'] == 'em_manutencao') ? 'selected' : ''; ?>>Em Manutenção</option>
                        <option value="baixada" <?php echo ($aeronave_data['status'] == 'baixada') ? 'selected' : ''; ?>>Baixada</option>
                        <option value="adida" <?php echo ($aeronave_data['status'] == 'adida') ? 'selected' : ''; ?>>Adida</option>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label> <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim" <?php echo (isset($aeronave_data['homologacao_anatel']) && $aeronave_data['homologacao_anatel'] == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="Não" <?php echo (isset($aeronave_data['homologacao_anatel']) && $aeronave_data['homologacao_anatel'] == 'Não') ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4"><?php echo htmlspecialchars($aeronave_data['info_adicionais'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="updateButton" type="submit" style="background-color:#007bff;">Atualizar Aeronave</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados da aeronave. Verifique se o ID está correto ou <a href="listar_aeronaves.php">volte para a lista</a>.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('editAeronaveForm')) {
        const modelosPorFabricante = <?php echo json_encode($fabricantes_e_modelos); ?>;
        const obmPorCrbm = <?php echo json_encode($unidades); ?>;

        const fabricanteSelect = document.getElementById('fabricante');
        const modeloSelect = document.getElementById('modelo');
        const crbmSelect = document.getElementById('crbm');
        const obmSelect = document.getElementById('obm');
        const prefixoSelect = document.getElementById('prefixo');

        const valorSalvo = {
            fabricante: "<?php echo addslashes($aeronave_data['fabricante'] ?? ''); ?>",
            modelo: "<?php echo addslashes($aeronave_data['modelo'] ?? ''); ?>",
            crbm: "<?php echo addslashes($aeronave_data['crbm'] ?? ''); ?>",
            obm: "<?php echo addslashes($aeronave_data['obm'] ?? ''); ?>",
            prefixo: "<?php echo addslashes($aeronave_data['prefixo'] ?? ''); ?>"
        };

        function atualizarModelos() {
            const fabricante = fabricanteSelect.value;
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            modeloSelect.disabled = true;

            if (fabricante && modelosPorFabricante[fabricante]) {
                modeloSelect.disabled = false;
                modelosPorFabricante[fabricante].forEach(function(modelo) {
                    const option = document.createElement('option');
                    option.value = modelo;
                    option.textContent = modelo;
                    if (modelo === valorSalvo.modelo) {
                        option.selected = true;
                    }
                    modeloSelect.appendChild(option);
                });
            }
        }

        function atualizarOBMs() {
            const crbm = crbmSelect.value;
            obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
            obmSelect.disabled = true;

            if (crbm && obmPorCrbm[crbm]) {
                obmSelect.disabled = false;
                obmPorCrbm[crbm].forEach(function(obm) {
                    const option = document.createElement('option');
                    option.value = obm;
                    option.textContent = obm;
                    if (obm === valorSalvo.obm) {
                        option.selected = true;
                    }
                    obmSelect.appendChild(option);
                });
            }
        }

        function gerarPrefixos() {
            prefixoSelect.innerHTML = '';
            const prefixosUsados = <?php echo json_encode($usados_prefixos); ?>;

            if (valorSalvo.prefixo) {
                const optionAtual = document.createElement('option');
                optionAtual.value = valorSalvo.prefixo;
                optionAtual.textContent = valorSalvo.prefixo;
                optionAtual.selected = true;
                prefixoSelect.appendChild(optionAtual);
            }

            for (let i = 1; i <= 50; i++) {
                const nomePrefixo = `HAWK ${i.toString().padStart(2, '0')}`;
                if (nomePrefixo === valorSalvo.prefixo) continue;

                const option = document.createElement('option');
                option.value = nomePrefixo;
                option.textContent = nomePrefixo;
                if (prefixosUsados.includes(nomePrefixo)) {
                    option.disabled = true;
                    option.textContent += ' (em uso)';
                }
                prefixoSelect.appendChild(option);
            }
        }

        fabricanteSelect.addEventListener('change', atualizarModelos);
        crbmSelect.addEventListener('change', atualizarOBMs);

        // Carga inicial dos dados
        atualizarModelos();
        atualizarOBMs();
        gerarPrefixos();
    }

    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            window.location.href = 'listar_aeronaves.php';
        }, 2000);
    }
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>