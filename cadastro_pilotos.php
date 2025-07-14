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
    // Coleta dos dados do formulário
    $posto_graduacao = htmlspecialchars($_POST['posto_graduacao']);
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
    $senha = $_POST['senha'];
    $tipo_usuario = htmlspecialchars($_POST['tipo_usuario']);

    $senha_redefinida = 0;
    $senha_hashed = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO pilotos (posto_graduacao, nome_completo, rg, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, senha, tipo_usuario, senha_redefinida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssssssi", $posto_graduacao, $nome_completo, $rg, $cpf, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $senha_hashed, $tipo_usuario, $senha_redefinida);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Piloto cadastrado com sucesso!</div>";
    } else {
        if ($conn->errno == 1062) {
            if (strpos($conn->error, "'rg'") !== false) {
                $mensagem_status = "<div class='error-message-box'>Erro: O RG informado já está cadastrado.</div>";
            } elseif (strpos($conn->error, "'cpf'") !== false) {
                $mensagem_status = "<div class='error-message-box'>Erro: O CPF informado já está cadastrado.</div>";
            } elseif (strpos($conn->error, "'email'") !== false) {
                $mensagem_status = "<div class='error-message-box'>Erro: O E-mail informado já está cadastrado.</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro: Um dos valores únicos (RG, CPF ou E-mail) já existe no sistema.</div>";
            }
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar piloto: " . htmlspecialchars($stmt->error) . "</div>";
        }
    }
    $stmt->close();
}
?>

<style>
    .form-grid-piloto {
        display: grid;
        grid-template-columns: 1fr 3fr;
        gap: 20px;
        align-items: flex-end;
    }
    @media (max-width: 768px) {
        .form-grid-piloto {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <h1>Cadastro de Pilotos</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form id="pilotoForm" action="cadastro_pilotos.php" method="POST">
            <div class="form-grid-piloto">
                <div class="form-group">
                    <label for="posto_graduacao">Posto/Graduação:</label>
                    <select id="posto_graduacao" name="posto_graduacao" required>
                        <option value="">Selecione...</option>
                        <option value="Cel. QOBM">Cel. QOBM</option>
                        <option value="Ten. Cel. QOBM">Ten. Cel. QOBM</option>
                        <option value="Maj. QOBM">Maj. QOBM</option>
                        <option value="Cap. QOBM">Cap. QOBM</option>
                        <option value="1º Ten. QOBM">1º Ten. QOBM</option>
                        <option value="2º Ten. QOBM">2º Ten. QOBM</option>
                        <option value="Asp. Oficial">Asp. Oficial</option>
                        <option value="Sub. Ten. QPBM">Sub. Ten. QPBM</option>
                        <option value="1º Sgt. QPBM">1º Sgt. QPBM</option>
                        <option value="2º Sgt. QPBM">2º Sgt. QPBM</option>
                        <option value="3º Sgt. QPBM">3º Sgt. QPBM</option>
                        <option value="Cb. QPBM">Cb. QPBM</option>
                        <option value="Sd. QPBM">Sd. QPBM</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nome_completo">Nome Completo:</label>
                    <input type="text" id="nome_completo" name="nome_completo" placeholder="Nome completo do piloto" required>
                </div>
            </div>
            <div class="form-grid">
                 <div class="form-group">
                    <label for="rg">RG:</label>
                    <input type="text" id="rg" name="rg" placeholder="Ex: X.XXX.XXX-X" required>
                </div>
                <div class="form-group">
                    <label for="cpf">CPF:</label>
                    <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Formato: 000.000.000-00" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" placeholder="email@exemplo.com" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="tel" id="telefone" name="telefone" placeholder="(XX) X XXXX-XXXX" pattern="\(\d{2}\) \d \d{4}-\d{4}" title="Formato: (XX) X XXXX-XXXX" required>
                </div>
                <div class="form-group">
                    <label for="crbm_piloto">CRBM:</label>
                    <select id="crbm_piloto" name="crbm_piloto" required>
                        <option value="">Selecione o CRBM</option>
                        <?php foreach (array_keys($unidades) as $crbm): ?>
                            <option value="<?php echo htmlspecialchars($crbm); ?>"><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm_piloto">OBM/Seção:</label>
                    <select id="obm_piloto" name="obm_piloto" required disabled>
                        <option value="">Selecione o CRBM Primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cadastro_sarpas">Código SARPAS:</label> <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" placeholder="Ex: AB2025123456" required>
                </div>
                <div class="form-group">
                    <label for="cparp">CPARP:</label>
                    <select id="cparp" name="cparp" required>
                        <option value="">Selecione</option>
                        <option value="SIM">SIM</option>
                        <option value="NAO">NÃO</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="senha">Senha Inicial:</label>
                    <input type="password" id="senha" name="senha" placeholder="Crie uma senha provisória" required>
                </div>
                <div class="form-group">
                    <label for="status_piloto">Status:</label>
                    <select id="status_piloto" name="status_piloto" required>
                        <option value="ativo">Ativo</option>
                        <option value="afastado">Afastado</option>
                        <option value="desativado">Desativado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Usuário:</label>
                    <select id="tipo_usuario" name="tipo_usuario" required>
                        <option value="">Selecione o Tipo</option>
                        <option value="administrador">Administrador</option>
                        <option value="piloto">Piloto</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais_piloto">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais_piloto" name="info_adicionais_piloto" rows="4" placeholder="Adicione qualquer informação relevante sobre o piloto..."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" id="saveButton" disabled>Salvar Piloto</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const obmPorCrbm = <?php echo json_encode($unidades); ?>;

    const form = document.getElementById('pilotoForm');
    const saveButton = document.getElementById('saveButton');
    const requiredFields = Array.from(form.querySelectorAll('[required]'));
    const crbmSelect = document.getElementById('crbm_piloto');
    const obmSelect = document.getElementById('obm_piloto');

    function checkFormValidity() {
        const allValid = requiredFields.every(field => field.disabled ? true : field.value.trim() !== '');
        saveButton.disabled = !allValid;
    }

    requiredFields.forEach(field => {
        field.addEventListener('input', checkFormValidity);
        field.addEventListener('change', checkFormValidity);
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

    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>