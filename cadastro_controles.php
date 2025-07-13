<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$aeronaves_disponiveis = [];
$sql_aeronaves = "SELECT id, prefixo, modelo, crbm, obm FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);
if ($result_aeronaves->num_rows > 0) {
    while($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}

// Busca fabricantes e modelos de CONTROLES do banco de dados
$fabricantes_e_modelos_controles = [];
$sql_modelos_ctrl = "SELECT fabricante, modelo FROM fabricantes_modelos WHERE tipo = 'Controle' ORDER BY CASE WHEN fabricante = 'DJI' THEN 1 WHEN fabricante = 'Autel Robotics' THEN 2 ELSE 3 END, fabricante, modelo";
$result_modelos_ctrl = $conn->query($sql_modelos_ctrl);
if ($result_modelos_ctrl) {
    while ($row = $result_modelos_ctrl->fetch_assoc()) {
        $fabricantes_e_modelos_controles[$row['fabricante']][] = $row['modelo'];
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


$mensagem_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fabricante = htmlspecialchars($_POST['fabricante']);
    $modelo = htmlspecialchars($_POST['modelo']);
    $numero_serie = htmlspecialchars($_POST['numero_serie']);
    $aeronave_id = !empty($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : NULL;
    $status = htmlspecialchars($_POST['status']);
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']);
    $data_aquisicao = htmlspecialchars($_POST['data_aquisicao']);
    $info_adicionais = htmlspecialchars($_POST['info_adicionais']);

    $crbm = '';
    $obm = '';

    if ($aeronave_id) {
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
        $crbm = htmlspecialchars($_POST['crbm']);
        $obm = htmlspecialchars($_POST['obm']);
    }

    $stmt = $conn->prepare("INSERT INTO controles (fabricante, modelo, numero_serie, aeronave_id, crbm, obm, status, homologacao_anatel, data_aquisicao, info_adicionais) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissssss", $fabricante, $modelo, $numero_serie, $aeronave_id, $crbm, $obm, $status, $homologacao_anatel, $data_aquisicao, $info_adicionais);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Controle cadastrado com sucesso!</div>";
    } else {
        if ($conn->errno == 1062) {
             $mensagem_status = "<div class='error-message-box'>Erro: O número de série informado já está cadastrado.</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar controle: " . htmlspecialchars($stmt->error) . "</div>";
        }
    }
    $stmt->close();
}
?>

<div class="main-content">
    <h1>Cadastro de Controles (Rádios)</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form id="controleForm" action="cadastro_controles.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="">Selecione o Fabricante</option>
                        <?php foreach (array_keys($fabricantes_e_modelos_controles) as $fab): ?>
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
                        <?php foreach (array_keys($unidades) as $crbm_unidade): ?>
                            <option value="<?php echo htmlspecialchars($crbm_unidade); ?>"><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm_unidade)); ?></option>
                        <?php endforeach; ?>
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
                    <label for="homologacao_anatel">Homologação ANATEL:</label> <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim">Sim</option>
                        <option value="Não" selected>Não</option>
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
    const modelosControlePorFabricante = <?php echo json_encode($fabricantes_e_modelos_controles); ?>;
    const obmPorCrbm = <?php echo json_encode($unidades); ?>;

    const form = document.getElementById('controleForm');
    const saveButton = document.getElementById('saveButton');
    const requiredFields = Array.from(form.querySelectorAll('[required]'));
    const fabricanteSelect = document.getElementById('fabricante');
    const modeloSelect = document.getElementById('modelo');
    const crbmSelect = document.getElementById('crbm');
    const obmSelect = document.getElementById('obm');
    const aeronaveSelect = document.getElementById('aeronave_id');

    function checkFormValidity() {
        const allValid = requiredFields.every(field => field.disabled ? true : field.value.trim() !== '');
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

            setTimeout(() => { obmSelect.value = obm; checkFormValidity(); }, 50); // Delay

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

    form.addEventListener('submit', function() {
        crbmSelect.disabled = false;
        obmSelect.disabled = false;
    });

    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>