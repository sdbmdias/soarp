<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Molde - Relatório de Pilotos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20mm; /* Margens para impressão */
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            height: auto;
        }
        .report-title {
            text-align: center;
            font-size: 1.5em;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        /* Estilos para impressão */
        @media print {
            body {
                margin: 10mm;
            }
            .header {
                margin-bottom: 10px;
            }
            .report-title {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="data:image/png;base64,<?php echo base64_encode(file_get_contents('logo1.png')); ?>" alt="Logo CBMPR" class="logo">
        <img src="data:image/png;base64,<?php echo base64_encode(file_get_contents('logo2.png')); ?>" alt="Logo SOARP" class="logo">
    </div>

    <h1 class="report-title">Relatório de Pilotos Cadastrados</h1>

    <table>
        <thead>
            <tr>
                <th>Posto/Grad.</th>
                <th>Nome Completo</th>
                <th>CPF</th>
                <th>CRBM</th>
                <th>OBM</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            </tbody>
    </table>

</body>
</html>