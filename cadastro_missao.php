<?php
require_once 'includes/header.php';
require_once 'gpx_parser.php'; 

$mensagem_status = "";

// 1. Busca aeronaves e tipos de ocorrência
$aeronaves_disponiveis = [];
$tipos_ocorrencia = [];

// Filtra aeronaves por CRBM se o usuário for piloto
if ($isPiloto) {
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    $crbm_do_piloto = $result_crbm->fetch_assoc()['crbm_piloto'];
    $stmt_crbm->close();
    
    $sql_aeronaves = "SELECT id, prefixo, modelo, crbm FROM aeronaves WHERE status = 'ativo' AND crbm = ? ORDER BY prefixo ASC";
    $stmt_aeronaves = $conn->prepare($sql_aeronaves);
    $stmt_aeronaves->bind_param("s", $crbm_do_piloto);
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
} else { // Admin vê todas
    $sql_aeronaves = "SELECT id, prefixo, modelo, crbm FROM aeronaves WHERE status = 'ativo' ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
}

if ($result_aeronaves) {
    while($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}

// Busca os tipos de ocorrência cadastrados
$result_ocorrencias = $conn->query("SELECT id, nome FROM tipos_ocorrencia ORDER BY nome ASC");
if($result_ocorrencias) {
    while($row = $result_ocorrencias->fetch_assoc()) {
        $tipos_ocorrencia[] = $row;
    }
}


// LÓGICA DE SUBMISSÃO DO FORMULÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_FILES['gpx_files']) && count(array_filter($_FILES['gpx_files']['name'])) > 0 && isset($_POST['pilotos']) && !empty($_POST['pilotos'])) {
        
        $conn->begin_transaction();
        try {
            $gpxProcessor = new GPXProcessor();
            foreach ($_FILES['gpx_files']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $gpxProcessor->load($tmp_name);
                }
            }
            
            $logData = $gpxProcessor->getAggregatedData();
            if ($logData === null) {
                throw new Exception("Não foi possível processar os ficheiros GPX.");
            }
            
            $aeronave_id = intval($_POST['aeronave_id']);
            $pilotos_selecionados = array_unique(array_filter($_POST['pilotos']));
            $data_ocorrencia = htmlspecialchars($_POST['data_ocorrencia']);
            $tipo_ocorrencia = htmlspecialchars($_POST['tipo_ocorrencia']);
            $protocolo_sarpas = htmlspecialchars($_POST['protocolo_sarpas']);
            $rgo_ocorrencia = htmlspecialchars($_POST['rgo_ocorrencia']);
            $dados_vitima = htmlspecialchars($_POST['dados_vitima']);

            $altitude_maxima = $logData['altitude_maxima'];
            $total_distancia_percorrida = $logData['total_distancia_percorrida'];
            $total_tempo_voo = $logData['total_tempo_voo'];
            $data_primeira_decolagem = $logData['data_primeira_decolagem'];
            $data_ultimo_pouso = $logData['data_ultimo_pouso'];

            $stmt_missao = $conn->prepare("INSERT INTO missoes (aeronave_id, data_ocorrencia, tipo_ocorrencia, protocolo_sarpas, rgo_ocorrencia, dados_vitima, altitude_maxima, total_distancia_percorrida, total_tempo_voo, data_primeira_decolagem, data_ultimo_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_missao->bind_param("isssssdidss", $aeronave_id, $data_ocorrencia, $tipo_ocorrencia, $protocolo_sarpas, $rgo_ocorrencia, $dados_vitima, $altitude_maxima, $total_distancia_percorrida, $total_tempo_voo, $data_primeira_decolagem, $data_ultimo_pouso);
            $stmt_missao->execute();
            $missao_id = $conn->insert_id;
            $stmt_missao->close();

            $stmt_pilotos_assoc = $conn->prepare("INSERT INTO missoes_pilotos (missao_id, piloto_id) VALUES (?, ?)");
            foreach ($pilotos_selecionados as $piloto_id) {
                $pid = intval($piloto_id);
                $stmt_pilotos_assoc->bind_param("ii", $missao_id, $pid);
                $stmt_pilotos_assoc->execute();
            }
            $stmt_pilotos_assoc->close();
            
            $stmt_gpx = $conn->prepare("INSERT INTO missoes_gpx_files (missao_id, file_name, file_path, tempo_voo, distancia_percorrida, altura_maxima, data_decolagem, data_pouso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_coords = $conn->prepare("INSERT INTO missao_coordenadas (gpx_file_id, latitude, longitude, altitude, timestamp_ponto) VALUES (?, ?, ?, ?, ?)");

            $individualFileData = $gpxProcessor->getIndividualFileData();
            foreach ($_FILES['gpx_files']['name'] as $key => $name) {
                if ($_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    
                    $file_log_data = $individualFileData[$key];
                    $file_path_db = ''; 

                    $tempo_voo_individual = $file_log_data['tempo_voo'];
                    $distancia_individual = $file_log_data['distancia_percorrida'];
                    $altura_maxima_individual = $file_log_data['altura_maxima'];
                    $decolagem_str = $file_log_data['data_decolagem']->format('Y-m-d H:i:s');
                    $pouso_str = $file_log_data['data_pouso']->format('Y-m-d H:i:s');
                    
                    $stmt_gpx->bind_param("issiddss", $missao_id, $name, $file_path_db, $tempo_voo_individual, $distancia_individual, $altura_maxima_individual, $decolagem_str, $pouso_str);
                    $stmt_gpx->execute();
                    $gpx_file_id = $conn->insert_id;

                    foreach ($file_log_data['trackPoints'] as $point) {
                        $lat = $point['lat'];
                        $lon = $point['lon'];
                        $ele = $point['ele'];
                        $time = $point['time']->format('Y-m-d H:i:s');
                        
                        $stmt_coords->bind_param("iddds", $gpx_file_id, $lat, $lon, $ele, $time);
                        $stmt_coords->execute();
                    }
                }
            }
            $stmt_gpx->close();
            $stmt_coords->close();
            
            $stmt_logbook = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE distancia_total_acumulada = distancia_total_acumulada + VALUES(distancia_total_acumulada), tempo_voo_total_acumulado = tempo_voo_total_acumulado + VALUES(tempo_voo_total_acumulado)");
            $stmt_logbook->bind_param("idi", $aeronave_id, $total_distancia_percorrida, $total_tempo_voo);
            $stmt_logbook->execute();
            $stmt_logbook->close();

            $conn->commit();
            $mensagem_status = "<div class='success-message-box'>Missão registrada com sucesso! Redirecionando...</div>";
            echo "<script>setTimeout(function() { window.location.href = 'listar_missoes.php'; }, 2000);</script>";

        } catch (Exception $e) {
            $conn->rollback();
            $mensagem_status = "<div class='error-message-box'>Erro ao registrar a missão: " . $e->getMessage() . "</div>";
        }

    } else {
        $mensagem_status = "<div class='error-message-box'>Erro: Preencha todos os campos obrigatórios, incluindo a seleção de pelo menos um piloto e um ficheiro GPX.</div>";
    }
}
?>

<div class="main-content">
    <h1>Registrar Nova Missão</h1>
    <?php echo $mensagem_status; ?>
    <div class="form-container">
        <form id="missaoForm" action="cadastro_missao.php" method="POST" enctype="multipart/form-data">
            <fieldset>
                <legend>1. Detalhes da Missão</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="aeronave_id">Aeronave (HAWK):</label>
                        <select id="aeronave_id" name="aeronave_id" required>
                            <option value="">Selecione a Aeronave</option>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option value="<?php echo $aeronave['id']; ?>" data-crbm="<?php echo htmlspecialchars($aeronave['crbm']); ?>">
                                    <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pilotos">Piloto(s):</label>
                        <div id="pilots-container">
                            <small>Selecione uma aeronave para carregar a lista de pilotos.</small>
                        </div>
                        <button type="button" id="add-pilot-btn" class="button-secondary" style="margin-top: 10px; display: none;">Adicionar outro Piloto</button>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data_ocorrencia">Data da Ocorrência:</label>
                        <input type="date" id="data_ocorrencia" name="data_ocorrencia" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_ocorrencia">Tipo da Ocorrência:</label>
                        <select id="tipo_ocorrencia" name="tipo_ocorrencia" required>
                            <option value="">Selecione o Tipo</option>
                            <?php foreach($tipos_ocorrencia as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['nome']); ?>"><?php echo htmlspecialchars($tipo['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="protocolo_sarpas">Protocolo SARPAS:</label>
                        <input type="text" id="protocolo_sarpas" name="protocolo_sarpas" placeholder="Ex: AS202407-1234" required>
                    </div>
                    <div class="form-group">
                        <label for="rgo_ocorrencia">Nº do RGO:</label>
                        <input type="text" id="rgo_ocorrencia" name="rgo_ocorrencia" placeholder="Ex: 123456/2025" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="dados_vitima">Dados da Vítima (Opcional):</label>
                        <textarea id="dados_vitima" name="dados_vitima" rows="3" placeholder="Informações relevantes sobre a vítima ou alvo da busca."></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Ficheiros de Log de Voo (GPX)</legend>
                <div class="form-group">
                    <label for="gpx_files">Selecione os ficheiros GPX da missão:</label>
                    <input type="file" id="gpx_files" name="gpx_files[]" accept=".gpx" multiple required>
                    <small>Pode selecionar vários ficheiros. Eles serão unificados.</small>
                    <div id="file-list" style="margin-top: 10px;"></div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" id="submitButton">Registrar Missão</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const aeronaveSelect = document.getElementById('aeronave_id');
    const pilotsContainer = document.getElementById('pilots-container');
    const addPilotBtn = document.getElementById('add-pilot-btn');
    let pilotList = [];

    aeronaveSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const crbm = selectedOption.getAttribute('data-crbm');
        
        // Limpa a seleção de pilotos e esconde o botão
        pilotsContainer.innerHTML = '<small>Carregando pilotos...</small>';
        addPilotBtn.style.display = 'none';

        if (crbm) {
            fetch(`get_pilotos.php?crbm=${crbm}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na rede ou no servidor.');
                    }
                    return response.json();
                })
                .then(data => {
                    pilotList = data;
                    pilotsContainer.innerHTML = ''; // Limpa a mensagem de "carregando"
                    if (pilotList.length > 0) {
                        addPilotSelect(); // Adiciona o primeiro select
                        addPilotBtn.style.display = 'inline-block'; // Mostra o botão
                    } else {
                        pilotsContainer.innerHTML = '<small>Nenhum piloto ativo encontrado para este CRBM.</small>';
                    }
                })
                .catch(error => {
                    pilotsContainer.innerHTML = `<small style="color: red;">Erro ao carregar pilotos: ${error.message}</small>`;
                });
        } else {
            pilotsContainer.innerHTML = '<small>Selecione uma aeronave para carregar a lista de pilotos.</small>';
        }
    });

    function addPilotSelect() {
        const selectWrapper = document.createElement('div');
        selectWrapper.style.display = 'flex';
        selectWrapper.style.alignItems = 'center';
        selectWrapper.style.marginBottom = '5px';

        const newSelect = document.createElement('select');
        newSelect.name = 'pilotos[]';
        newSelect.required = true;
        
        let optionsHtml = '<option value="">Selecione um piloto</option>';
        pilotList.forEach(piloto => {
            optionsHtml += `<option value="${piloto.id}">${piloto.nome_completo}</option>`;
        });
        newSelect.innerHTML = optionsHtml;

        selectWrapper.appendChild(newSelect);

        // Adicionar botão de remover, exceto para o primeiro select
        if (pilotsContainer.children.length > 0) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remover';
            removeBtn.style.marginLeft = '10px';
            removeBtn.style.backgroundColor = '#dc3545';
            removeBtn.style.color = 'white';
            removeBtn.style.border = 'none';
            removeBtn.style.padding = '5px 10px';
            removeBtn.style.borderRadius = '4px';
            removeBtn.style.cursor = 'pointer';
            removeBtn.onclick = function() {
                pilotsContainer.removeChild(selectWrapper);
            };
            selectWrapper.appendChild(removeBtn);
        }
        
        pilotsContainer.appendChild(selectWrapper);
    }

    addPilotBtn.addEventListener('click', addPilotSelect);

    // Validação para não permitir submeter pilotos duplicados
    const form = document.getElementById('missaoForm');
    form.addEventListener('submit', function(e) {
        const selectedPilots = Array.from(document.querySelectorAll('select[name="pilotos[]"]')).map(s => s.value);
        const uniquePilots = new Set(selectedPilots);

        if (selectedPilots.length > uniquePilots.size) {
            e.preventDefault();
            alert('Erro: Por favor, não selecione o mesmo piloto mais de uma vez.');
        }
    });

    // Exibe os nomes dos arquivos selecionados
    const gpxInput = document.getElementById('gpx_files');
    const fileListDiv = document.getElementById('file-list');
    gpxInput.addEventListener('change', function() {
        fileListDiv.innerHTML = '';
        if (this.files.length > 0) {
            const list = document.createElement('ul');
            list.style.paddingLeft = '20px';
            for (const file of this.files) {
                const item = document.createElement('li');
                item.textContent = file.name;
                list.appendChild(item);
            }
            fileListDiv.appendChild(list);
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>