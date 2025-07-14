<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";

// 2. LÓGICA PARA BUSCAR DADOS PARA OS DROPDOWNS
$aeronaves_disponiveis = [];
$pilotos_disponiveis = [];

// Busca aeronaves que o piloto pode operar ou todas se for admin
if ($isAdmin) {
    $sql_aeronaves = "SELECT id, prefixo, modelo FROM aeronaves WHERE status = 'ativo' ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
} else {
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    $stmt_obm->close();

    if (!empty($obm_do_piloto)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, modelo FROM aeronaves WHERE status = 'ativo' AND obm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $obm_do_piloto);
        $stmt_aeronaves->execute();
        $result_aeronaves = $stmt_aeronaves->get_result();
    }
}
if (isset($result_aeronaves) && $result_aeronaves->num_rows > 0) {
    while($row = $result_aeronaves->fetch_assoc()) $aeronaves_disponiveis[] = $row;
}

// Busca pilotos (admin vê todos, piloto vê a si mesmo)
if ($isAdmin) {
    $sql_pilotos = "SELECT id, nome_completo FROM pilotos WHERE status_piloto = 'ativo' ORDER BY nome_completo ASC";
    $result_pilotos = $conn->query($sql_pilotos);
} else {
    $sql_pilotos = $conn->prepare("SELECT id, nome_completo FROM pilotos WHERE id = ?");
    $sql_pilotos->bind_param("i", $_SESSION['user_id']);
    $sql_pilotos->execute();
    $result_pilotos = $sql_pilotos->get_result();
}
if (isset($result_pilotos) && $result_pilotos->num_rows > 0) {
    while($row = $result_pilotos->fetch_assoc()) $pilotos_disponiveis[] = $row;
}

// 3. LÓGICA PARA PROCESSAR O FORMULÁRIO QUANDO ENVIADO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Coleta de dados do formulário
        $aeronave_id = intval($_POST['aeronave_id']);
        $piloto_id = intval($_POST['piloto_id']);
        $data_ocorrencia = htmlspecialchars($_POST['data_ocorrencia']);
        $tipo_ocorrencia = htmlspecialchars($_POST['tipo_ocorrencia']);
        $rgo_ocorrencia = htmlspecialchars($_POST['rgo_ocorrencia']);
        $dados_vitima = htmlspecialchars($_POST['dados_vitima']);

        // Coleta de dados calculados pelo JavaScript
        $altitude_maxima = floatval($_POST['altitude_maxima']);
        $altitude_minima = floatval($_POST['altitude_minima']);
        $total_distancia = floatval($_POST['total_distancia_percorrida']);
        $total_tempo = intval($_POST['total_tempo_voo']); // em segundos
        $primeira_decolagem = htmlspecialchars($_POST['data_primeira_decolagem']);
        $ultimo_pouso = htmlspecialchars($_POST['data_ultimo_pouso']);
        $dji_flight_ids_json = $_POST['dji_flight_ids'];

        // Inserir na tabela 'missoes'
        $stmt_missao = $conn->prepare("INSERT INTO missoes (aeronave_id, piloto_id, data_ocorrencia, tipo_ocorrencia, rgo_ocorrencia, dados_vitima, altitude_maxima, altitude_minima, total_distancia_percorrida, total_tempo_voo, data_primeira_decolagem, data_ultimo_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_missao->bind_param("iisssssddiss", $aeronave_id, $piloto_id, $data_ocorrencia, $tipo_ocorrencia, $rgo_ocorrencia, $dados_vitima, $altitude_maxima, $altitude_minima, $total_distancia, $total_tempo, $primeira_decolagem, $ultimo_pouso);
        $stmt_missao->execute();
        $missao_id = $conn->insert_id;
        $stmt_missao->close();

        // Inserir os voos da DJI associados
        $dji_flight_ids = json_decode($dji_flight_ids_json, true);
        $stmt_dji_voos = $conn->prepare("INSERT INTO missoes_voos_dji (missao_id, dji_flight_id) VALUES (?, ?)");
        foreach ($dji_flight_ids as $flight_id) {
            $stmt_dji_voos->bind_param("is", $missao_id, $flight_id);
            $stmt_dji_voos->execute();
        }
        $stmt_dji_voos->close();

        // Atualizar (ou inserir) o logbook cumulativo da aeronave
        $stmt_logbook = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE distancia_total_acumulada = distancia_total_acumulada + VALUES(distancia_total_acumulada), tempo_voo_total_acumulado = tempo_voo_total_acumulado + VALUES(tempo_voo_total_acumulado)");
        $stmt_logbook->bind_param("idi", $aeronave_id, $total_distancia, $total_tempo);
        $stmt_logbook->execute();
        $stmt_logbook->close();

        $conn->commit();
        $mensagem_status = "<div class='success-message-box'>Missão registrada com sucesso! Redirecionando...</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_status = "<div class='error-message-box'>Erro ao registrar a missão: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="main-content">
    <h1>Registrar Nova Missão</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form id="missaoForm" action="cadastro_missao.php" method="POST">
            <fieldset>
                <legend>1. Detalhes da Missão</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="aeronave_id">Aeronave (HAWK):</label>
                        <select id="aeronave_id" name="aeronave_id" required>
                            <option value="">Selecione a Aeronave</option>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option value="<?php echo $aeronave['id']; ?>"><?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="piloto_id">Piloto Responsável:</label>
                        <select id="piloto_id" name="piloto_id" required <?php if (!$isAdmin) echo 'disabled'; ?>>
                            <?php foreach ($pilotos_disponiveis as $piloto): ?>
                                <option value="<?php echo $piloto['id']; ?>" <?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $piloto['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($piloto['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="data_ocorrencia">Data da Ocorrência:</label>
                        <input type="date" id="data_ocorrencia" name="data_ocorrencia" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_ocorrencia">Tipo da Ocorrência:</label>
                        <input type="text" id="tipo_ocorrencia" name="tipo_ocorrencia" placeholder="Ex: Busca e salvamento, Incêndio florestal" required>
                    </div>
                    <div class="form-group">
                        <label for="rgo_ocorrencia">Nº do RGO (se aplicável):</label>
                        <input type="text" id="rgo_ocorrencia" name="rgo_ocorrencia" placeholder="Ex: 123456/2025">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="dados_vitima">Dados da Vítima (se aplicável):</label>
                        <textarea id="dados_vitima" name="dados_vitima" rows="3" placeholder="Informações relevantes sobre a vítima ou alvo da busca."></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Voos da Missão</legend>
                <div class="form-group">
                    <p>Carregue os voos registrados na DJI Cloud que fazem parte desta missão.</p>
                    <button type="button" id="loadDjiFlights" class="button-secondary" disabled>
                        <i class="fas fa-cloud-download-alt"></i> Carregar Voos da DJI Cloud
                    </button>
                    <div id="djiFlightsList" style="margin-top: 15px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background-color: #f9f9f9;">
                        <small>Selecione uma aeronave para carregar os voos.</small>
                    </div>
                </div>
                 <div class="form-group">
                    <button type="button" id="calculateTotals" class="button-secondary" disabled>
                        <i class="fas fa-calculator"></i> Calcular Totais do Voo
                    </button>
                </div>
            </fieldset>

            <fieldset>
                <legend>3. Logbook da Missão (Dados Calculados)</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Altitude Máxima (metros):</label>
                        <input type="number" id="altitude_maxima" name="altitude_maxima" readonly required>
                    </div>
                    <div class="form-group">
                        <label>Altitude Mínima (metros):</label>
                        <input type="number" id="altitude_minima" name="altitude_minima" readonly required>
                    </div>
                    <div class="form-group">
                        <label>Distância Total Percorrida (metros):</label>
                        <input type="number" id="total_distancia_percorrida" name="total_distancia_percorrida" readonly required>
                    </div>
                    <div class="form-group">
                        <label>Tempo Total de Voo (minutos):</label>
                        <input type="text" id="total_tempo_voo_display" readonly>
                        <input type="hidden" id="total_tempo_voo" name="total_tempo_voo" required>
                    </div>
                    <div class="form-group">
                        <label>Data/Hora da Primeira Decolagem:</label>
                        <input type="datetime-local" id="data_primeira_decolagem" name="data_primeira_decolagem" readonly required>
                    </div>
                     <div class="form-group">
                        <label>Data/Hora do Último Pouso:</label>
                        <input type="datetime-local" id="data_ultimo_pouso" name="data_ultimo_pouso" readonly required>
                    </div>
                </div>
                <input type="hidden" id="dji_flight_ids" name="dji_flight_ids">
            </fieldset>

            <div class="form-actions">
                <button type="submit" id="submitButton" disabled>Registrar Missão</button>
            </div>
        </form>
    </div>
</div>

<style>
    fieldset { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px; }
    legend { font-weight: 700; color: #34495e; padding: 0 10px; }
    .button-secondary { background-color: #6c757d; color: #fff; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
    .button-secondary:disabled { background-color: #c8cbcf; cursor: not-allowed; }
    #djiFlightsList label { display: block; margin-bottom: 5px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const aeronaveSelect = document.getElementById('aeronave_id');
    const loadDjiFlightsBtn = document.getElementById('loadDjiFlights');
    const djiFlightsListDiv = document.getElementById('djiFlightsList');
    const calculateTotalsBtn = document.getElementById('calculateTotals');
    const form = document.getElementById('missaoForm');
    const submitButton = document.getElementById('submitButton');

    aeronaveSelect.addEventListener('change', function() {
        if (this.value) {
            loadDjiFlightsBtn.disabled = false;
            djiFlightsListDiv.innerHTML = '<small>Clique em "Carregar Voos" para ver os registros.</small>';
        } else {
            loadDjiFlightsBtn.disabled = true;
            djiFlightsListDiv.innerHTML = '<small>Selecione uma aeronave para carregar os voos.</small>';
        }
    });

    loadDjiFlightsBtn.addEventListener('click', function() {
        djiFlightsListDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';

        // --- SIMULAÇÃO DE CHAMADA À API DA DJI ---
        // No futuro, isso seria uma chamada AJAX (fetch) para um script PHP que se conecta à API real.
        setTimeout(() => {
            const mockFlights = [
                { id: 'dji_flight_1', takeoff: '2025-07-15T14:05:00', land: '2025-07-15T14:25:00', duration: 1200, dist: 1500, max_alt: 120, min_alt: 20 },
                { id: 'dji_flight_2', takeoff: '2025-07-15T14:35:00', land: '2025-07-15T14:50:00', duration: 900, dist: 1100, max_alt: 110, min_alt: 15 },
                { id: 'dji_flight_3', takeoff: '2025-07-14T09:10:00', land: '2025-07-14T09:35:00', duration: 1500, dist: 2500, max_alt: 150, min_alt: 30 }
            ];
            
            djiFlightsListDiv.innerHTML = ''; // Limpa o "Carregando"
            mockFlights.forEach(flight => {
                const container = document.createElement('div');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'dji-flight-checkbox';
                checkbox.dataset.flight = JSON.stringify(flight);
                
                const label = document.createElement('label');
                const takeoffDate = new Date(flight.takeoff);
                label.textContent = ` Voo de ${takeoffDate.toLocaleDateString()} às ${takeoffDate.toLocaleTimeString()} (${Math.round(flight.duration / 60)} min)`;
                
                container.appendChild(checkbox);
                container.appendChild(label);
                djiFlightsListDiv.appendChild(container);
            });
            
            calculateTotalsBtn.disabled = false;
        }, 1500); // Simula um delay de rede
    });

    calculateTotalsBtn.addEventListener('click', function() {
        const selectedCheckboxes = document.querySelectorAll('.dji-flight-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
            alert('Por favor, selecione pelo menos um voo.');
            return;
        }

        let totalDist = 0, totalTime = 0;
        let maxAlt = -Infinity, minAlt = Infinity;
        let firstTakeoff = null, lastLand = null;
        const selectedIds = [];

        selectedCheckboxes.forEach(cb => {
            const flight = JSON.parse(cb.dataset.flight);
            selectedIds.push(flight.id);
            totalDist += flight.dist;
            totalTime += flight.duration;
            if (flight.max_alt > maxAlt) maxAlt = flight.max_alt;
            if (flight.min_alt < minAlt) minAlt = flight.min_alt;
            
            const takeoffDate = new Date(flight.takeoff);
            const landDate = new Date(flight.land);

            if (!firstTakeoff || takeoffDate < firstTakeoff) firstTakeoff = takeoffDate;
            if (!lastLand || landDate > lastLand) lastLand = landDate;
        });

        // Preenche os campos do formulário
        document.getElementById('altitude_maxima').value = maxAlt;
        document.getElementById('altitude_minima').value = minAlt;
        document.getElementById('total_distancia_percorrida').value = totalDist;
        document.getElementById('total_tempo_voo').value = totalTime;
        document.getElementById('total_tempo_voo_display').value = `${Math.floor(totalTime / 60)}h ${totalTime % 60}min`;
        
        // Formata data e hora para o input datetime-local (YYYY-MM-DDTHH:mm)
        if (firstTakeoff) document.getElementById('data_primeira_decolagem').value = firstTakeoff.toISOString().slice(0, 16);
        if (lastLand) document.getElementById('data_ultimo_pouso').value = lastLand.toISOString().slice(0, 16);
        
        document.getElementById('dji_flight_ids').value = JSON.stringify(selectedIds);
        
        submitButton.disabled = false;
    });
    
    // Redirecionamento após sucesso
    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            window.location.href = 'listar_missoes.php';
        }, 2000);
    }
});
</script>


<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>