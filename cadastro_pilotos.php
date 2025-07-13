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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    $stmt = $conn->prepare("INSERT INTO pilotos (nome_completo, rg, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, senha, tipo_usuario, senha_redefinida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssssi", $nome_completo, $rg, $cpf, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $senha_hashed, $tipo_usuario, $senha_redefinida);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Piloto cadastrado com sucesso!</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar piloto: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}
?>

<div class="main-content">
    <h1>Cadastro de Pilotos</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form id="pilotoForm" action="cadastro_pilotos.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="nome_completo">Nome Completo:</label>
                    <input type="text" id="nome_completo" name="nome_completo" placeholder="Nome completo do piloto" required>
                </div>
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
    const obmPorCrbm = {
        'CCB': ['BM-1', 'BM-2', 'BM-3', 'BM-4', 'BM-5', 'BM-6', 'BM-7', 'BM-8'], 'BOA': ['SOARP'], 'GOST': ['GOST'],
        '1CRBM': ['1º BBM', '6º BBM', '7º BBM', '8º BBM'], '2CRBM': ['3º BBM', '11º BBM', '1ª CIBM'],
        '3CRBM': ['4º BBM', '9º BBM', '10º BBM', '13º BBM'], '4CRBM': ['5º BBM', '2ª CIBM', '4ª CIBM', '5ª CIBM'],
        '5CRBM': ['2º BBM', '12º BBM', '6ª CIBM']
    };

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
        checkFormValidity();
    });
    
    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>