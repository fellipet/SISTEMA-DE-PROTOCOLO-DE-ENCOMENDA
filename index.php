<?php
/**
 * Sistema de Protocolo de Encomendas (SdP-E)
 */

session_start();
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "sistema_protocolo";

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    $tab_query = "CREATE TABLE IF NOT EXISTS encomendas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        destinatario VARCHAR(255) NOT NULL,
        cep VARCHAR(10),
        endereco TEXT,
        documento VARCHAR(100),
        responsavel_entrega VARCHAR(255),
        protocolista VARCHAR(255),
        valor DECIMAL(10,2),
        rastreio VARCHAR(100),
        data_envio DATE,
        status ENUM('Aguardando Postagem', 'Protocolado') DEFAULT 'Aguardando Postagem'
    )";
    $pdo->exec($tab_query);
} catch (PDOException $e) { die("Erro: " . $e->getMessage()); }

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); }
$is_admin = isset($_SESSION['logado']);

// EXCLUSÃO
if ($is_admin && isset($_GET['excluir'])) {
    $stmt = $pdo->prepare("DELETE FROM encomendas WHERE id = ?");
    $stmt->execute([$_GET['excluir']]);
    header("Location: index.php");
}

// LOGIN
if (isset($_POST['login'])) {
    if ($_POST['user'] == 'protocolo' && $_POST['pass'] == 'sdfelipe') {
        $_SESSION['logado'] = true; header("Location: index.php");
    }
}

// SALVAR/EDITAR
if ($is_admin && isset($_POST['salvar'])) {
    $status = (empty($_POST['rastreio']) || empty($_POST['data_envio'])) ? 'Aguardando Postagem' : 'Protocolado';
    $valor = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor'] ?? '0');
    
    if (!empty($_POST['id'])) {
        $sql = "UPDATE encomendas SET destinatario=?, cep=?, endereco=?, documento=?, responsavel_entrega=?, protocolista=?, valor=?, rastreio=?, data_envio=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['destinatario'], $_POST['cep'], $_POST['endereco'], $_POST['documento'], $_POST['responsavel_entrega'], $_POST['protocolista'], $valor, $_POST['rastreio'], $_POST['data_envio'], $status, $_POST['id']]);
    } else {
        $sql = "INSERT INTO encomendas (destinatario, cep, endereco, documento, responsavel_entrega, protocolista, valor, rastreio, data_envio, status) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['destinatario'], $_POST['cep'], $_POST['endereco'], $_POST['documento'], $_POST['responsavel_entrega'], $_POST['protocolista'], $valor, $_POST['rastreio'], $_POST['data_envio'], $status]);
    }
    header("Location: index.php");
}

// JSON RELATÓRIO
if (isset($_GET['gerar_relatorio'])) {
    $inicio = $_GET['inicio']; $fim = $_GET['fim'];
    $stmt = $pdo->prepare("SELECT * FROM encomendas WHERE data_envio BETWEEN ? AND ? ORDER BY data_envio DESC");
    $stmt->execute([$inicio, $fim]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SdP-E | 2º BEC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f9fafb; color: #1f2937; }
        .header-clean { background-color: #ffffff; border-bottom: 2px solid #e5e7eb; }
        .text-militar { color: #1a4731; }
        .bg-militar { background-color: #1a4731; }
        input, textarea, select { border: 1px solid #d1d5db !important; padding: 10px; border-radius: 8px; width: 100%; }
        .search-input { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="%239ca3af" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>'); background-repeat: no-repeat; background-position: 12px center; padding-left: 40px !important; }
    </style>
</head>
<body>

    <header class="header-clean py-8 mb-8 no-print text-center">
        <img src="image_b48588.png" alt="Logo 2º BEC" class="h-24 mx-auto mb-4">
        <h1 class="text-2xl md:text-3xl font-black text-gray-800 uppercase tracking-tighter">2º Batalhão de Engenharia de Construção</h1>
        <p class="text-[10px] font-bold text-militar mt-1 tracking-[0.3em] uppercase">SdP-E - Sistema de Protocolo de Encomendas</p>
        
        <div class="mt-6 flex justify-center gap-3">
            <?php if (!$is_admin): ?>
                <button onclick="document.getElementById('modalLogin').classList.remove('hidden')" class="text-xs font-bold text-gray-400 hover:text-militar transition uppercase tracking-widest">Acesso Restrito</button>
            <?php else: ?>
                <button onclick="document.getElementById('modalRelatorio').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-blue-700">RELATÓRIOS</button>
                <a href="?logout" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-red-700">SAIR</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 pb-12">
        
        <div class="bg-white p-4 rounded-xl mb-6 shadow-sm border border-gray-200 flex flex-col md:flex-row gap-4 items-center no-print">
            <div class="w-full md:w-2/3">
                <input type="text" id="inputBusca" onkeyup="filtrarTabela()" placeholder="Pesquisar por Destinatário ou Nº Documento..." class="search-input">
            </div>
            <div class="w-full md:w-1/3">
                <select id="filtroStatus" onchange="filtrarTabela()">
                    <option value="">Todos os Status</option>
                    <option value="Protocolado">Protocolados</option>
                    <option value="Aguardando Postagem">Aguardando Postagem</option>
                </select>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <div class="bg-white p-6 rounded-xl mb-8 shadow-sm border border-gray-200 no-print">
                <h2 class="text-lg font-bold mb-4 text-militar border-b pb-2">Novo Registro</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="text" name="destinatario" id="edit_dest" placeholder="Destinatário" required>
                    <input type="text" name="cep" id="edit_cep" placeholder="CEP" maxlength="9">
                    <input type="text" name="documento" id="edit_doc" placeholder="Nº Documento" required>
                    <input type="text" name="responsavel_entrega" id="edit_resp" placeholder="Responsável Entrega">
                    <input type="text" name="protocolista" id="edit_prot" placeholder="Protocolista" required>
                    <input type="text" name="valor" id="edit_val" placeholder="Valor R$">
                    <input type="text" name="rastreio" id="edit_rast" placeholder="Código de Rastreio">
                    <input type="date" name="data_envio" id="edit_data">
                    <textarea name="endereco" id="edit_end" placeholder="Endereço Completo" class="md:col-span-2"></textarea>
                    <div class="md:col-span-3 flex gap-2">
                        <button type="submit" name="salvar" class="flex-1 bg-militar text-white font-bold py-3 rounded-lg hover:bg-black transition shadow-lg">SALVAR PROTOCOLO</button>
                        <button type="button" onclick="window.location.reload()" class="bg-gray-200 text-gray-600 px-6 rounded-lg font-bold uppercase text-xs">Limpar</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
            <table class="w-full text-left" id="tabelaProtocolos">
                <thead class="bg-gray-50 text-[10px] text-gray-400 uppercase font-bold">
                    <tr>
                        <th class="p-4">Data/Status</th>
                        <th class="p-4">Destinatário</th>
                        <th class="p-4">Nº Documento</th>
                        <th class="p-4">Rastreio</th>
                        <th class="p-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    $res = $pdo->query("SELECT * FROM encomendas ORDER BY data_envio DESC, id DESC");
                    while ($row = $res->fetch(PDO::FETCH_ASSOC)):
                        $st_style = ($row['status'] == 'Protocolado') ? 'text-green-600 bg-green-50' : 'text-orange-600 bg-orange-50';
                    ?>
                    <tr class="hover:bg-gray-50 transition item-protocolo">
                        <td class="p-4">
                            <span class="text-xs font-bold text-gray-900"><?= $row['data_envio'] ? date('d/m/Y', strtotime($row['data_envio'])) : '--/--/--' ?></span><br>
                            <span class="<?= $st_style ?> px-2 py-0.5 rounded text-[9px] font-bold uppercase status-label"><?= $row['status'] ?></span>
                        </td>
                        <td class="p-4">
                            <div class="text-sm font-bold text-gray-800 uppercase busca-alvo"><?= $row['destinatario'] ?></div>
                        </td>
                        <td class="p-4">
                            <div class="text-xs text-gray-600 font-bold busca-alvo"><?= $row['documento'] ?></div>
                        </td>
                        <td class="p-4 font-mono text-xs font-bold text-blue-600"><?= $row['rastreio'] ?: '---' ?></td>
                        <td class="p-4 text-center space-x-2 text-gray-300">
                            <button onclick='imprimirCupom(<?= json_encode($row) ?>)' title="Imprimir" class="hover:text-gray-800"><i class="fas fa-print"></i></button>
                            <?php if ($is_admin): ?>
                                <button onclick='preencherEdicao(<?= json_encode($row) ?>)' title="Editar" class="hover:text-militar"><i class="fas fa-edit"></i></button>
                                <button onclick="confirmarExclusao(<?= $row['id'] ?>)" title="Excluir" class="hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div id="semResultados" class="hidden p-10 text-center text-gray-400 italic">Nenhum registro encontrado.</div>
        </div>
    </main>

    <div id="modalRelatorio" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 no-print">
        <div class="bg-white p-6 rounded-2xl w-full max-w-sm">
            <h2 class="text-xl font-bold mb-4 text-center uppercase text-gray-500">Relatório</h2>
            <div class="space-y-4 mb-6">
                <div><label class="text-[10px] font-bold text-gray-400 uppercase">Início</label><input type="date" id="rel_inicio"></div>
                <div><label class="text-[10px] font-bold text-gray-400 uppercase">Fim</label><input type="date" id="rel_fim"></div>
            </div>
            <button onclick="gerarRelatorioFull()" class="w-full bg-militar text-white py-3 rounded-xl font-bold">GERAR PDF</button>
            <button onclick="document.getElementById('modalRelatorio').classList.add('hidden')" class="w-full mt-3 text-gray-400 text-xs">Fechar</button>
        </div>
    </div>

    <div id="modalLogin" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white p-8 rounded-3xl w-full max-w-xs text-center">
            <h2 class="font-black mb-6 uppercase text-gray-400 text-xs tracking-widest">Identificação</h2>
            <form method="POST">
                <input type="text" name="user" placeholder="Usuário" class="mb-3" required>
                <input type="password" name="pass" placeholder="Senha" class="mb-6" required>
                <button type="submit" name="login" class="w-full bg-militar text-white py-3 rounded-xl font-bold">ACESSAR</button>
                <button type="button" onclick="document.getElementById('modalLogin').classList.add('hidden')" class="w-full mt-4 text-gray-300 text-[10px] uppercase font-bold">Voltar</button>
            </form>
        </div>
    </div>

    <footer class="mt-8 text-center text-[15px] text-gray-300 no-print pb-12 uppercase font-bold tracking-[0.4em]">
        DESENVOLVIDO POR SD FELIPE ALENCAR
    </footer>

    <script>
        function confirmarExclusao(id) {
            if (confirm("Deseja excluir este registro permanentemente?")) {
                window.location.href = "index.php?excluir=" + id;
            }
        }

        function filtrarTabela() {
            var busca = document.getElementById("inputBusca").value.toLowerCase();
            var statusSel = document.getElementById("filtroStatus").value.toLowerCase().trim();
            var linhas = document.getElementsByClassName("item-protocolo");
            var encontrou = 0;

            for (var i = 0; i < linhas.length; i++) {
                var alvos = linhas[i].getElementsByClassName("busca-alvo");
                var textoStatus = linhas[i].getElementsByClassName("status-label")[0].innerText.toLowerCase().trim();
                var conteudoBusca = alvos[0].innerText.toLowerCase() + " " + alvos[1].innerText.toLowerCase();
                var atendeBusca = conteudoBusca.indexOf(busca) > -1;
                var atendeStatus = (statusSel === "" || textoStatus === statusSel);

                if (atendeBusca && atendeStatus) {
                    linhas[i].style.display = ""; encontrou++;
                } else {
                    linhas[i].style.display = "none";
                }
            }
            document.getElementById("semResultados").classList.toggle("hidden", encontrou > 0);
        }

        function preencherEdicao(d) {
            document.getElementById('edit_id').value = d.id;
            document.getElementById('edit_dest').value = d.destinatario;
            document.getElementById('edit_cep').value = d.cep;
            document.getElementById('edit_doc').value = d.documento;
            document.getElementById('edit_resp').value = d.responsavel_entrega;
            document.getElementById('edit_prot').value = d.protocolista;
            document.getElementById('edit_val').value = d.valor;
            document.getElementById('edit_rast').value = d.rastreio;
            document.getElementById('edit_data').value = d.data_envio;
            document.getElementById('edit_end').value = d.endereco;
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function imprimirCupom(d) {
            var dataF = d.data_envio ? d.data_envio.split('-').reverse().join('/') : '--/--/--';
            var win = window.open('', '', 'width=600,height=500');
            var selo = (d.status === 'Protocolado') ? '<div style="border:3px solid green; color:green; padding:5px; transform:rotate(-15deg); display:inline-block; font-weight:bold; font-size:14px; margin-top:10px;">POSTADO / CONFERIDO</div>' : '<div style="border:3px solid orange; color:orange; padding:5px; transform:rotate(-15deg); display:inline-block; font-weight:bold; font-size:14px; margin-top:10px;">PENDENTE</div>';
            var conteudo = '<html><body style="font-family:sans-serif; padding:20px; text-align:center; border:1px solid #000; width:300px; margin:auto;">';
            conteudo += '<img src="image_b48588.png" style="height:60px; margin-bottom:10px;">';
            conteudo += '<h4 style="margin:0; font-size:12px;">2º BATALHÃO DE ENGENHARIA DE CONSTRUÇÃO</h4><hr>';
            conteudo += '<div style="text-align:left; font-size:11px; line-height:1.6;"><p><strong>DATA:</strong> '+dataF+'</p><p><strong>DESTINO:</strong> '+d.destinatario+'</p><p><strong>DOC:</strong> '+d.documento+'</p><p><strong>RASTREIO:</strong> '+(d.rastreio || "Pendente")+'</p><p><strong>PROTOCOLISTA:</strong> '+d.protocolista+'</p></div>';
            conteudo += selo + '<hr><p style="font-size:9px;">Protocolo SdP-E emitido eletronicamente.</p><script>window.print();<\/script></body></html>';
            win.document.write(conteudo); win.document.close();
        }

        function gerarRelatorioFull() {
            var ini = document.getElementById('rel_inicio').value;
            var fim = document.getElementById('rel_fim').value;
            if(!ini || !fim) return alert("Datas necessárias!");
            fetch('index.php?gerar_relatorio=1&inicio=' + ini + '&fim=' + fim)
                .then(r => r.json()).then(dados => {
                    var win = window.open('', '', 'width=1000,height=800');
                    var linhas = '';
                    dados.forEach(d => {
                        linhas += `<tr>
                            <td style="border:1px solid #ddd; padding:6px;">${d.data_envio.split('-').reverse().join('/')}</td>
                            <td style="border:1px solid #ddd; padding:6px; text-transform:uppercase;">${d.destinatario}</td>
                            <td style="border:1px solid #ddd; padding:6px;">${d.documento}</td>
                            <td style="border:1px solid #ddd; padding:6px;">${d.rastreio || '---'}</td>
                            <td style="border:1px solid #ddd; padding:6px; font-weight:bold; color:${d.status === 'Protocolado' ? 'green' : 'orange'}">${d.status.toUpperCase()}</td>
                        </tr>`;
                    });
                    var html = `<html><body style="font-family:sans-serif; padding:40px;"><center><img src="image_b48588.png" style="height:80px;"><h2>2º BEC</h2><h3>RELATÓRIO DE ENVIO</h3></center><table style="width:100%; border-collapse:collapse; font-size:10px; margin-top:20px;"><thead><tr style="background:#eee;"><th>DATA</th><th>DESTINATÁRIO</th><th>DOC</th><th>RASTREIO</th><th>STATUS</th></tr></thead><tbody>${linhas}</tbody></table><script>window.print();<\/script></body></html>`;
                    win.document.write(html); win.document.close();
                });
        }
    </script>
</body>
</html>