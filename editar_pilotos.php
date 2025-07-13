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
$piloto_data = null;

$piloto_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['piloto_id']) ? intval($_POST['piloto_id']) : null);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $piloto_id) {
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
    $info_adicionais_piloto = htmlspecialchars($_POST['info_adicionais']);
    $tipo_usuario = htmlspecialchars($_POST['tipo_usuario']);

    $stmt = $conn->prepare("UPDATE pilotos SET nome_completo=?, rg=?, cpf=?, email=?, telefone=?, crbm_piloto=?, obm_piloto=?, cadastro_sarpas=?, cparp=?, status_piloto=?, info_adicionais=?, tipo_usuario=? WHERE id = ?");
    $stmt->bind_param("ssssssssssssi", $nome_completo, $rg, $cpf, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $tipo_usuario, $piloto_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Piloto atualizado com sucesso! Redirecionando...</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar piloto: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}

if ($piloto_id) {
    $stmt_load = $conn->prepare("SELECT id, nome_completo, rg, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, tipo_usuario FROM pilotos WHERE id = ?");
    $stmt_load->bind_param("i", $piloto_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $piloto_data = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div class='error-message-box'>Piloto não encontrado.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID do piloto não fornecido para edição.</div>";
    }
}
?>

<div class="main-content">
    <h1>Editar Piloto</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($piloto_data): ?>
    <div class="form-container">
        <form id="editPilotoForm" action="editar_pilotos.php?id=<?php echo htmlspecialchars($piloto_id); ?>" method="POST">
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
                    <select id="obm_piloto" name="obm_piloto" required> </select>
                </div>
                <div class="form-group">
                    <label for="cadastro_sarpas">Código SARPAS:</label> <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" value="<?php echo htmlspecialchars($piloto_data['cadastro_sarpas']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cparp">CPARP:</label>
                    <select id="cparp" name="cparp" required>
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
                        <option value="administrador" <?php echo ($piloto_data['tipo_usuario'] == 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                        <option value="piloto" <?php echo ($piloto_data['tipo_usuario'] == 'piloto') ? 'selected' : ''; ?>>Piloto</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4"><?php echo htmlspecialchars($piloto_data['info_adicionais'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="updateButton" type="submit" style="background-color:#007bff;">Atualizar Piloto</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados do piloto. <a href="listar_pilotos.php">Volte para a lista</a>.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('editPilotoForm')) {
        const obmPorCrbm = {
            'CCB': ['BM-1', 'BM-2', 'BM-3', 'BM-4', 'BM-5', 'BM-6', 'BM-7', 'BM-8'], 'BOA': ['SOARP'], 'GOST': ['GOST'],
            '1CRBM': ['1º BBM', '6º BBM', '7º BBM', '8º BBM'], '2CRBM': ['3º BBM', '11º BBM', '1ª CIBM'],
            '3CRBM': ['4º BBM', '9º BBM', '10º BBM', '13º BBM'], '4CRBM': ['5º BBM', '2ª CIBM', '4ª CIBM', '5ª CIBM'],
            '5CRBM': ['2º BBM', '12º BBM', '6ª CIBM']
        };

        const crbmSelect = document.getElementById('crbm_piloto');
        const obmSelect = document.getElementById('obm_piloto');
        const valorSalvoOBM = "<?php echo addslashes($piloto_data['obm_piloto'] ?? ''); ?>";

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
            obmSelect.value = valorSalvoOBM;
        }

        crbmSelect.addEventListener('change', atualizarOBMs);
        atualizarOBMs();
    }

    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            window.location.href = 'listar_pilotos.php';
        }, 2000); // 2 segundos
    }
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>