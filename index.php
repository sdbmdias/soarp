<?php
session_start(); // Inicia a sessão para armazenar informações do usuário

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

$login_error = ""; // Variável para armazenar mensagens de erro de login

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_cpf = htmlspecialchars($_POST['cpf']);
    $input_password = $_POST['password'];

    // Simples validação para evitar processamento desnecessário
    if (empty($input_cpf) || empty($input_password)) {
        $login_error = "CPF e Senha são campos obrigatórios.";
    } else {
        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("Falha na conexão com o banco de dados: " . $conn->connect_error);
        }

        // Query para buscar o piloto pelo CPF
        $stmt = $conn->prepare("SELECT id, nome_completo, senha, status_piloto, tipo_usuario, senha_redefinida FROM pilotos WHERE cpf = ?");
        $stmt->bind_param("s", $input_cpf);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $piloto = $result->fetch_assoc();
            
            if (password_verify($input_password, $piloto['senha'])) {
                if ($piloto['status_piloto'] == 'ativo') {
                    
                    $update_login_stmt = $conn->prepare("UPDATE pilotos SET ultimo_acesso = NOW() WHERE id = ?");
                    $update_login_stmt->bind_param("i", $piloto['id']);
                    $update_login_stmt->execute();
                    $update_login_stmt->close();

                    $_SESSION['user_id'] = $piloto['id'];
                    $_SESSION['user_name'] = $piloto['nome_completo'];
                    $_SESSION['user_cpf'] = $input_cpf;
                    $_SESSION['user_type'] = $piloto['tipo_usuario'];
                    
                    if ($piloto['senha_redefinida'] == 0) {
                        $_SESSION['force_password_reset'] = true;
                        header("Location: primeiro_acesso.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();

                } else {
                    $login_error = "Sua conta está " . htmlspecialchars($piloto['status_piloto']) . ". Entre em contato com o administrador.";
                }
            } else {
                $login_error = "Credenciais inválidas. Verifique seu CPF e senha.";
            }
        } else {
            $login_error = "Credenciais inválidas. Verifique seu CPF e senha.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYSARP - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
            background-color: #f0f2f5;
            overflow: hidden;
        }

        /* Coluna da Esquerda (Imagens) */
        .image-section {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .image-section .img-left {
            flex: 1;
            background-size: cover;
            background-position: center;
        }

        /* Coluna da Direita (Login) */
        .login-section {
            flex: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            box-sizing: border-box;
            position: relative;
            background: url('img/direita-unica.jpg') center center/cover no-repeat;
        }

        .login-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.3);
            z-index: 1;
        }
        
        .login-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 400px;
            background-color: rgba(255, 255, 255, 0.85);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        .logo-container {
            margin-bottom: 25px;
        }

        .logo-container img {
            height: 140px;
            margin-bottom: 10px;
        }

        .logo-text {
            font-size: 2.8em;
            font-weight: bold;
            color: #c0392b;
            letter-spacing: 2px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-with-icon .icon {
            position: absolute;
            left: 15px;
            color: #888;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #c0392b;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: #c0392b;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: #a93226;
        }
        
        .forgot-password-link {
            display: block;
            margin-top: 20px;
            font-size: 0.9em;
            color: #555;
            text-decoration: none;
        }
        .forgot-password-link:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        /* NOVO ESTILO ADICIONADO */
        .org-text {
            margin-top: 25px;
            font-size: 0.8em;
            color: #666;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .image-section {
                display: none;
            }
            .login-section {
                flex: 1;
            }
        }

    </style>
</head>
<body>

    <div class="image-section">
        <div class="img-left" style="background-image: url('img/esquerda-superior.jpg');"></div>
        <div class="img-left" style="background-image: url('img/esquerda-inferior.jpg');"></div>
    </div>

    <div class="login-section">
        <div class="login-overlay"></div>
        <div class="login-content">
            <div class="logo-container">
                <img src="img/logo_soarp.png" alt="Logo SYSARP">
                <div class="logo-text">SYSARP</div>
            </div>

            <form action="index.php" method="POST">
                
                <?php if (!empty($login_error)): ?>
                    <div class="error-message"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <div class="input-group">
                    <label for="cpf">CPF</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user icon"></i>
                        <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Formato: 000.000.000-00" oninput="formatCPF(this)">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Senha</label>
                     <div class="input-with-icon">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" id="password" name="password" placeholder="Sua senha" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">ENTRAR</button>
                
                <a href="esqueci_senha.php" class="forgot-password-link">Esqueceu sua senha?</a>

                <div class="org-text">
                    Seção Operacional de Aeronaves Remotamente Pilotadas - SOARP/CBMPR
                </div>
                
            </form>
        </div>
    </div>
    
    <script>
        function formatCPF(cpfInput) {
            let value = cpfInput.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            cpfInput.value = value;
        }
    </script>
</body>
</html>