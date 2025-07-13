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

$usados_prefixos = [];
if ($aeronave_id) {
    $sql_used_prefixes = "SELECT prefixo FROM aeronaves WHERE id != ?";
    $stmt_prefixes = $conn->prepare($sql_used_prefixes);
    if ($stmt_prefixes) {
        $stmt_prefixes->bind_param("i", $aeronave_id);
        $stmt_prefixes->execute();
        $result_used_prefixes = $stmt_prefixes->get_result();
        while ($row_prefix = $result_used_prefixes->fetch_assoc()) {
            $usados_prefixos[] = $row_prefix['prefixo'];
        }
        $stmt_prefixes->close();
    }
}

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
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']); // NOVO CAMPO
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
                        <option value="DJI" <?php echo ($aeronave_data['fabricante'] == 'DJI') ? 'selected' : ''; ?>>DJI</option>
                        <option value="Autel Robotics" <?php echo ($aeronave_data['fabricante'] == 'Autel Robotics') ? 'selected' : ''; ?>>Autel Robotics</option>
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
                        <option value="CCB" <?php echo ($aeronave_data['crbm'] == 'CCB') ? 'selected' : ''; ?>>CCB</option>
                        <option value="BOA" <?php echo ($aeronave_data['crbm'] == 'BOA') ? 'selected' : ''; ?>>BOA</option>
                        <option value="GOST" <?php echo ($aeronave_data['crbm'] == 'GOST') ? 'selected' : ''; ?>>GOST</option>
                        <option value="1CRBM" <?php echo ($aeronave_data['crbm'] == '1CRBM') ? 'selected' : ''; ?>>1º CRBM</option>
                        <option value="2CRBM" <?php echo ($aeronave_data['crbm'] == '2CRBM') ? 'selected' : ''; ?>>2º CRBM</option>
                        <option value="3CRBM" <?php echo ($aeronave_data['crbm'] == '3CRBM') ? 'selected' : ''; ?>>3º CRBM</option>
                        <option value="4CRBM" <?php echo ($aeronave_data['crbm'] == '4CRBM') ? 'selected' : ''; ?>>4º CRBM</option>
                        <option value="5CRBM" <?php echo ($aeronave_data['crbm'] == '5CRBM') ? 'selected' : ''; ?>>5º CRBM</option>
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
        const modelosPorFabricante = {
            'DJI': [ 'DJI FlyCart 30', 'DJI FlyCart 100', 'DJI Mini 3 Pro', 'DJI Mini 4 Pro', 'Matrice 30 Thermal (M30T)', 'Matrice 300 RTK', 'Matrice 350 RTK', 'Mavic 2 Enterprise', 'Mavic 2 Enterprise Advanced', 'Mavic 3 Classic', 'Mavic 3 Enterprise (M3E)', 'Mavic 3 Multispectral (M3M)', 'Mavic 3 Pro', 'Mavic 3 Thermal (M3T)', 'Phantom 3', 'Phantom 4 Pro V2.0', 'Phantom 4 RTK' ],
            'Autel Robotics': [ 'Dragonfish Lite', 'Dragonfish Pro', 'Dragonfish Standard', 'EVO II Dual 640T (V1/V2)', 'EVO II Dual 640T V3', 'EVO II Enterprise V3', 'EVO II Pro (V1/V2)', 'EVO II Pro V3', 'EVO Lite+', 'EVO MAX 4N', 'EVO MAX 4T', 'EVO Nano+' ]
        };
        const obmPorCrbm = {
            'CCB': ['BM-1', 'BM-2', 'BM-3', 'BM-4', 'BM-5', 'BM-6', 'BM-7', 'BM-8'], 'BOA': ['SOARP'], 'GOST': ['GOST'],
            '1CRBM': ['1º BBM', '6º BBM', '7º BBM', '8º BBM'], '2CRBM': ['3º BBM', '11º BBM', '1ª CIBM'],
            '3CRBM': ['4º BBM', '9º BBM', '10º BBM', '13º BBM'], '4CRBM': ['5º BBM', '2ª CIBM', '4ª CIBM', '5ª CIBM'],
            '5CRBM': ['2º BBM', '12º BBM', '6ª CIBM']
        };

        const fabricanteSelect = document.getElementById('fabricante');
        const modeloSelect = document.getElementById('modelo');
        const crbmSelect = document.getElementById('crbm');
        const obmSelect = document.getElementById('obm');
        const prefixoSelect = document.getElementById('prefixo');

        const valorSalvo = {
            modelo: "<?php echo addslashes($aeronave_data['modelo'] ?? ''); ?>",
            obm: "<?php echo addslashes($aeronave_data['obm'] ?? ''); ?>",
            prefixo: "<?php echo addslashes($aeronave_data['prefixo'] ?? ''); ?>"
        };

        function atualizarModelos() {
            const fabricante = fabricanteSelect.value;
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            if (fabricante && modelosPorFabricante[fabricante]) {
                modelosPorFabricante[fabricante].forEach(function(modelo) {
                    const option = document.createElement('option');
                    option.value = modelo;
                    option.textContent = modelo;
                    modeloSelect.appendChild(option);
                });
            }
            modeloSelect.value = valorSalvo.modelo;
        }

        function atualizarOBMs() {
            const crbm = crbmSelect.value;
            obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
            if (crbm && obmPorCrbm[crbm]) {
                obmPorCrbm[crbm].forEach(function(obm) {
                    const option = document.createElement('option');
                    option.value = obm;
                    option.textContent = obm;
                    obmSelect.appendChild(option);
                });
            }
            obmSelect.value = valorSalvo.obm;
        }

        function gerarPrefixos() {
            prefixoSelect.innerHTML = '';
            const prefixosUsados = <?php echo json_encode($usados_prefixos); ?>;
            
            if (valorSalvo.prefixo) {
                const optionAtual = document.createElement('option');
                optionAtual.value = valorSalvo.prefixo;
                optionAtual.textContent = valorSalvo.prefixo;
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
            prefixoSelect.value = valorSalvo.prefixo;
        }

        fabricanteSelect.addEventListener('change', atualizarModelos);
        crbmSelect.addEventListener('change', atualizarOBMs);

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