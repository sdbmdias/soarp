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

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Falha na conexão com o banco de dados: " . $conn->connect_error);
    }

    // Query modificada para buscar a nova coluna 'senha_redefinida'
    $stmt = $conn->prepare("SELECT id, nome_completo, senha, status_piloto, tipo_usuario, senha_redefinida FROM pilotos WHERE cpf = ?");
    $stmt->bind_param("s", $input_cpf);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $piloto = $result->fetch_assoc();
        
        if (password_verify($input_password, $piloto['senha'])) {
            if ($piloto['status_piloto'] == 'ativo') {
                
                // *** INÍCIO DA NOVA ALTERAÇÃO ***
                // Atualiza a data e hora do último acesso no banco de dados
                $update_login_stmt = $conn->prepare("UPDATE pilotos SET ultimo_acesso = NOW() WHERE id = ?");
                $update_login_stmt->bind_param("i", $piloto['id']);
                $update_login_stmt->execute();
                $update_login_stmt->close();
                // *** FIM DA NOVA ALTERAÇÃO ***

                // Login bem-sucedido, armazena dados na sessão
                $_SESSION['user_id'] = $piloto['id'];
                $_SESSION['user_name'] = $piloto['nome_completo'];
                $_SESSION['user_cpf'] = $input_cpf;
                $_SESSION['user_type'] = $piloto['tipo_usuario'];
                
                // ** LÓGICA DE REDIRECIONAMENTO **
                // Verifica se a senha precisa ser redefinida
                if ($piloto['senha_redefinida'] == 0) { // '0' ou 'FALSE'
                    // É o primeiro acesso, força a redefinição de senha
                    $_SESSION['force_password_reset'] = true;
                    header("Location: primeiro_acesso.php");
                } else {
                    // Não é o primeiro acesso, vai para o dashboard
                    header("Location: dashboard.php");
                }
                exit();

            } else {
                $login_error = "Sua conta está " . $piloto['status_piloto'] . ". Por favor, entre em contato com o administrador.";
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f2f5;
            color: #333;
            background-image: url('background_image.png');
            background-repeat: no-repeat;
            background-position: center;        
            background-size: contain; 
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #f0f2f5;
            opacity: 0.3;
            z-index: -1;
            background-image: url('background_image.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
            z-index: 1;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }

        .login-container:hover {
            opacity: 1;
        }

        .login-container h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container h1 img {
            height: 40px;
            margin-right: 10px;
            vertical-align: middle;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .input-group input {
            width: calc(100% - 20px);
            padding: 12px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            color: #333;
        }

        .input-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .login-button {
            background-color: #3498db;
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            width: 100%;
            transition: background-color 0.3s ease;
        }

        .login-button:hover {
            background-color: #2980b9;
        }

        .error-message {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .forgot-password-link {
            display: block;
            margin-top: 15px;
            font-size: 14px;
            color: #555;
            text-decoration: none;
        }
        .forgot-password-link:hover {
            text-decoration: underline;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>
            <img src="logo_soarp.png" alt="Logo SOARP">
            SOARP - CBMPR
        </h1>

        <?php if (!empty($login_error)): ?>
            <p class="error-message"><?php echo $login_error; ?></p>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="input-group">
                <label for="cpf"><i class="fas fa-user"></i> CPF:</label>
                <input type="text" id="cpf" name="cpf" placeholder="Seu CPF" required pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Formato: 000.000.000-00">
            </div>
            <div class="input-group">
                <label for="password"><i class="fas fa-lock"></i> Senha:</label>
                <input type="password" id="password" name="password" placeholder="Sua senha" required>
            </div>
            <button type="submit" class="login-button">Entrar</button>
            <a href="esqueci_senha.php" class="forgot-password-link">Esqueceu a Senha?</a>
        </form>
    </div>
</body>
</html>