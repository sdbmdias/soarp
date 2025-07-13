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
$controle_data = null;

$controle_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['controle_id']) ? intval($_POST['controle_id']) : null);

$aeronaves_disponiveis = [];
$sql_aeronaves = "SELECT id, prefixo, modelo, crbm, obm FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);
if ($result_aeronaves->num_rows > 0) {
    while($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $controle_id) {
    $fabricante = htmlspecialchars($_POST['fabricante']);
    $modelo = htmlspecialchars($_POST['modelo']);
    $numero_serie = htmlspecialchars($_POST['numero_serie']);
    $aeronave_id = !empty($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : NULL;
    $status = htmlspecialchars($_POST['status']);
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']); // NOVO CAMPO
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

    $stmt = $conn->prepare("UPDATE controles SET fabricante=?, modelo=?, numero_serie=?, aeronave_id=?, crbm=?, obm=?, status=?, homologacao_anatel=?, data_aquisicao=?, info_adicionais=? WHERE id = ?");
    $stmt->bind_param("sssissssssi", $fabricante, $modelo, $numero_serie, $aeronave_id, $crbm, $obm, $status, $homologacao_anatel, $data_aquisicao, $info_adicionais, $controle_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Controle atualizado com sucesso! Redirecionando...</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar controle: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}

if ($controle_id) {
    $stmt_load = $conn->prepare("SELECT * FROM controles WHERE id = ?");
    $stmt_load->bind_param("i", $controle_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $controle_data = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div class='error-message-box'>Controle não encontrado.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID do controle não fornecido para edição.</div>";
    }
}
?>

<div class="main-content">
    <h1>Editar Controle (Rádio)</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($controle_data): ?>
    <div class="form-container">
        <form id="editControleForm" action="editar_controles.php?id=<?php echo htmlspecialchars($controle_id); ?>" method="POST">
            <input type="hidden" name="controle_id" value="<?php echo htmlspecialchars($controle_data['id']); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="DJI" <?php echo ($controle_data['fabricante'] == 'DJI') ? 'selected' : ''; ?>>DJI</option>
                        <option value="Autel Robotics" <?php echo ($controle_data['fabricante'] == 'Autel Robotics') ? 'selected' : ''; ?>>Autel Robotics</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <select id="modelo" name="modelo" required></select>
                </div>
                <div class="form-group">
                    <label for="numero_serie">Número de Série:</label>
                    <input type="text" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($controle_data['numero_serie']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="aeronave_id">Vincular à Aeronave (Prefixo):</label>
                    <select id="aeronave_id" name="aeronave_id">
                        <option value="">Nenhuma (Controle Reserva)</option>
                        <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                            <option value="<?php echo htmlspecialchars($aeronave['id']); ?>"
                                <?php echo ($controle_data['aeronave_id'] == $aeronave['id']) ? 'selected' : ''; ?>
                                data-crbm="<?php echo htmlspecialchars($aeronave['crbm']); ?>"
                                data-obm="<?php echo htmlspecialchars($aeronave['obm']); ?>">
                                <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="crbm">CRBM de Lotação:</label>
                    <select id="crbm" name="crbm" required>
                        <option value="">Selecione o CRBM</option>
                        <option value="CCB" <?php echo ($controle_data['crbm'] == 'CCB') ? 'selected' : ''; ?>>CCB</option>
                        <option value="BOA" <?php echo ($controle_data['crbm'] == 'BOA') ? 'selected' : ''; ?>>BOA</option>
                        <option value="GOST" <?php echo ($controle_data['crbm'] == 'GOST') ? 'selected' : ''; ?>>GOST</option>
                        <option value="1CRBM" <?php echo ($controle_data['crbm'] == '1CRBM') ? 'selected' : ''; ?>>1º CRBM</option>
                        <option value="2CRBM" <?php echo ($controle_data['crbm'] == '2CRBM') ? 'selected' : ''; ?>>2º CRBM</option>
                        <option value="3CRBM" <?php echo ($controle_data['crbm'] == '3CRBM') ? 'selected' : ''; ?>>3º CRBM</option>
                        <option value="4CRBM" <?php echo ($controle_data['crbm'] == '4CRBM') ? 'selected' : ''; ?>>4º CRBM</option>
                        <option value="5CRBM" <?php echo ($controle_data['crbm'] == '5CRBM') ? 'selected' : ''; ?>>5º CRBM</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm">OBM/Seção de Lotação:</label>
                    <select id="obm" name="obm" required></select>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?php echo ($controle_data['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="em_manutencao" <?php echo ($controle_data['status'] == 'em_manutencao') ? 'selected' : ''; ?>>Em Manutenção</option>
                        <option value="baixado" <?php echo ($controle_data['status'] == 'baixado') ? 'selected' : ''; ?>>Baixado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label> <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim" <?php echo (isset($controle_data['homologacao_anatel']) && $controle_data['homologacao_anatel'] == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="Não" <?php echo (isset($controle_data['homologacao_anatel']) && $controle_data['homologacao_anatel'] == 'Não') ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="data_aquisicao">Data de Aquisição:</label>
                    <input type="date" id="data_aquisicao" name="data_aquisicao" value="<?php echo htmlspecialchars($controle_data['data_aquisicao']); ?>" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4"><?php echo htmlspecialchars($controle_data['info_adicionais'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="updateButton" type="submit" style="background-color:#007bff;">Atualizar Controle</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados do controle. <a href="listar_controles.php">Volte para a lista</a>.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('editControleForm')) {
        const modelosControlePorFabricante = {
            'DJI': ['RC', 'RC 2', 'RC Pro', 'RC Plus', 'Smart Controller'],
            'Autel Robotics': ['Smart Controller V3', 'Smart Controller SE']
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
        const aeronaveSelect = document.getElementById('aeronave_id');

        const valorSalvo = {
            modelo: "<?php echo addslashes($controle_data['modelo'] ?? ''); ?>",
            obm: "<?php echo addslashes($controle_data['obm'] ?? ''); ?>"
        };

        function atualizarModelos() {
            const fabricante = fabricanteSelect.value;
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            if (fabricante && modelosControlePorFabricante[fabricante]) {
                modelosControlePorFabricante[fabricante].forEach(function(modelo) {
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

        function toggleLotacaoFields() {
            const isReserva = aeronaveSelect.value === "";
            crbmSelect.disabled = !isReserva;
            obmSelect.disabled = !isReserva;
            if(!isReserva){
                const selectedOption = aeronaveSelect.options[aeronaveSelect.selectedIndex];
                const crbm = selectedOption.getAttribute('data-crbm');
                const obm = selectedOption.getAttribute('data-obm');
                crbmSelect.value = crbm;
                crbmSelect.dispatchEvent(new Event('change'));
                setTimeout(() => { obmSelect.value = obm; }, 50); // Delay
            }
        }

        fabricanteSelect.addEventListener('change', atualizarModelos);
        crbmSelect.addEventListener('change', atualizarOBMs);
        aeronaveSelect.addEventListener('change', toggleLotacaoFields);

        document.getElementById('editControleForm').addEventListener('submit', function() {
            crbmSelect.disabled = false;
            obmSelect.disabled = false;
        });
        
        // Carga inicial
        atualizarModelos();
        atualizarOBMs();
        toggleLotacaoFields();
    }
    
    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            window.location.href = 'listar_controles.php';
        }, 2000);
    }
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>