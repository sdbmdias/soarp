<?php
// 1. INCLUI O CABEÇALHO PADRÃO
// Essa linha cuida da sessão, segurança, conexão com o banco e menu.
require_once 'includes/header.php';
?>

<div class="main-content">
    <h1>Manutenção de Aeronaves</h1>

    <div class="maintenance-container">
        <h2>Próximas Manutenções Agendadas</h2>
        <ul class="maintenance-list">
            <li>
                <div>
                    <strong>Drone:</strong> HAWK 05 (DJI Mavic 3 Thermal) <br>
                    <strong>Tipo de Manutenção:</strong> Revisão Anual Obrigatória <br>
                    <strong>Data Prevista:</strong> 25/08/2025
                </div>
                <div class="maintenance-actions">
                    <button>Ver Detalhes</button>
                </div>
            </li>
            <li>
                <div>
                    <strong>Drone:</strong> HAWK 12 (Autel EVO Max 4T) <br>
                    <strong>Tipo de Manutenção:</strong> Troca de Baterias Principais <br>
                    <strong>Data Prevista:</strong> 10/09/2025
                </div>
                <div class="maintenance-actions">
                    <button>Ver Detalhes</button>
                </div>
            </li>
            <li>
                <div>
                    <strong>Drone:</strong> HAWK 01 (DJI Air 3) <br>
                    <strong>Tipo de Manutenção:</strong> Calibração de Sensores <br>
                    <strong>Data Prevista:</strong> 01/10/2025
                </div>
                <div class="maintenance-actions">
                    <button>Ver Detalhes</button>
                </div>
            </li>
        </ul>

        <h2 style="margin-top: 40px;">Histórico de Manutenções</h2>
        <p>Esta seção exibirá um histórico detalhado de todas as manutenções realizadas em cada aeronave.</p>
        
        <h2 style="margin-top: 40px;">Registrar Nova Manutenção</h2>
        <p>Formulário para registrar uma nova manutenção realizada ou agendada.</p>
    </div>
</div>

<?php
// 3. INCLUI O RODAPÉ PADRÃO
require_once 'includes/footer.php';
?>