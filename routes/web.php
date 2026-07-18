<?php

use App\Http\Controllers\AluguerController;
use App\Http\Controllers\ContasBancariaController;
use App\Http\Controllers\AluguerOpcaoController;
use App\Http\Controllers\SalaoController;
use App\Http\Controllers\BarController;
use App\Http\Controllers\CaixaDiariaController;
use App\Http\Controllers\ClientePedidoController;
use App\Http\Controllers\FuncionarioPedidoController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\FaturaCompraController;
use App\Http\Controllers\FestaContaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpressoraController;
use App\Http\Controllers\InscricaoController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoItemController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PosBarController;
use App\Http\Controllers\PosCotasController;
use App\Http\Controllers\PosPainelController;
use App\Http\Controllers\PosLoginController;
use App\Http\Controllers\PosReservasController;
use App\Http\Controllers\PosRestController;
use App\Http\Controllers\PrecarioController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\SecaoController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\SponsorAdminController;
use App\Http\Controllers\SponsorImageController;
use App\Http\Controllers\SponsorScreenController;
use App\Http\Controllers\SponsorshipController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\ChamadaComissaoController;
use App\Http\Controllers\ReservaPublicaController;
use App\Http\Controllers\ValorExtraController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sobre-nos', [PagesController::class, 'sobreNos'])->name('pages.sobre-nos');
Route::get('/patrocinios', [SponsorshipController::class, 'index'])->name('patrocinios.index');
Route::post('/patrocinios', [SponsorshipController::class, 'store'])->middleware('throttle:8,1')->name('patrocinios.store');
Route::get('/ecra-patrocinios', SponsorScreenController::class)->name('patrocinios.ecra');
Route::get('/ecra-reservas', [PosReservasController::class, 'ecra'])->name('ecra-reservas');
Route::get('/politica-de-privacidade', [LegalController::class, 'privacidade'])->name('legal.privacidade');
Route::get('/termos-e-condicoes', [LegalController::class, 'termos'])->name('legal.termos');
Route::get('/politica-de-cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/precario', PrecarioController::class)->name('precario');

// Reserva pública — página do cliente para subscrever notificações push
Route::get('/reserva/{token}', [ReservaPublicaController::class, 'show'])->name('reserva.publica');
Route::post('/reserva/{token}/subscrever', [ReservaPublicaController::class, 'subscribe'])->name('reserva.subscribe');
Route::post('/reserva/{token}/dessubscrever', [ReservaPublicaController::class, 'unsubscribe'])->name('reserva.unsubscribe');
Route::post('/contacto', ContactController::class)->middleware('throttle:8,1')->name('contacto.store');

// Pré-reserva pública do salão
Route::get('/reserva-salao', [SalaoController::class, 'show'])->name('salao.pre-reserva');
Route::post('/reserva-salao', [SalaoController::class, 'store'])->middleware('throttle:5,1')->name('salao.pre-reserva.store');
Route::get('/evento/{evento}', [EventoController::class, 'publicShow'])->name('eventos.public.show');
// URL fixa para o QR dos cartazes — nunca muda
Route::get('/inscricoes', [InscricaoController::class, 'index'])->name('inscricoes.index');
Route::get('/inscricoes/pagamento/retorno', [InscricaoController::class, 'pagamentoRetorno'])->name('inscricoes.pagamento.retorno');
Route::get('/inscricoes/pagamento/falha', [InscricaoController::class, 'pagamentoFalha'])->name('inscricoes.pagamento.falha');
Route::post('/inscricoes/{evento}', [InscricaoController::class, 'store'])->middleware('throttle:10,1')->name('inscricoes.store');
Route::get('/cliente/{token}', [ClientePedidoController::class, 'show'])->name('cliente.mesa');
Route::post('/cliente/{token}/item', [ClientePedidoController::class, 'addItem'])->name('cliente.item');
Route::post('/cliente/{token}/items', [ClientePedidoController::class, 'addItems'])->name('cliente.items');
Route::get('/chamar/{token}', [ClientePedidoController::class, 'showChamar'])->name('cliente.chamar.show');
Route::post('/cliente/{token}/chamar', [ClientePedidoController::class, 'chamarFuncionario'])->name('cliente.chamar');
Route::get('/cliente/{token}/confirmacao', [ClientePedidoController::class, 'confirmacao'])->name('cliente.confirmacao');
Route::get('/funcionario/{token}', [FuncionarioPedidoController::class, 'show'])->name('funcionario.mesa');
Route::post('/funcionario/{token}/items', [FuncionarioPedidoController::class, 'addItems'])->name('funcionario.items');
Route::get('/funcionario/{token}/estado', [ClientePedidoController::class, 'estado'])->name('funcionario.estado');
Route::post('/funcionario/{token}/confirmar-chamada', [ClientePedidoController::class, 'confirmarChamada'])->name('funcionario.confirmar-chamada');

Route::get('/pos/login', [PosLoginController::class, 'show'])->name('pos.login');
Route::post('/pos/login', [PosLoginController::class, 'store'])->name('pos.login.store');
Route::post('/pos/login/comissao', [PosLoginController::class, 'comissaoStore'])->name('pos.login.comissao');
Route::post('/pos/login/comissao/sair', [PosLoginController::class, 'comissaoDestroy'])->name('pos.login.comissao.sair');
Route::post('/pos/logout', [PosLoginController::class, 'destroy'])->name('pos.logout');

// Rotas partilhadas por TODOS os tipos de POS (o prefixo pos-comum não é
// restringido por tipo no EnsurePosSession, ao contrário de pos/, pos-rest/, etc.)
Route::middleware('pos.auth')->prefix('pos-comum')->name('pos.comum.')->group(function () {
    Route::post('/chamar-comissao', [ChamadaComissaoController::class, 'store'])->name('comissao.chamar');
    Route::get('/chamadas-funcionario', [ClientePedidoController::class, 'chamadasPos'])->name('chamadas');
    Route::post('/chamadas-funcionario/{pedido}/confirmar', [ClientePedidoController::class, 'confirmarChamadaPos'])->name('chamadas.confirmar');
    // Só para POS em modo comissão: ver e atender chamadas à comissão
    Route::get('/chamadas-comissao', [ChamadaComissaoController::class, 'pendentesPos'])->name('comissao.pendentes');
    Route::post('/chamadas-comissao/{chamada}/atender', [ChamadaComissaoController::class, 'atenderPos'])->name('comissao.atender');
});

Route::middleware('pos.auth')->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosBarController::class, 'index'])->name('index');
    Route::post('/prepago', [PosBarController::class, 'storePrepago'])->name('prepago.store');
    Route::get('/pedido/{pedido}/talao', [PosBarController::class, 'talao'])->name('pedido.talao');
    // Compatibilidade com builds antigos (bar/café)
    Route::post('/chamar-comissao', [ChamadaComissaoController::class, 'store'])->name('comissao.chamar.antigo');
});

Route::middleware('pos.auth')->prefix('pos-rest')->name('pos.rest.')->group(function () {
    Route::get('/', [PosRestController::class, 'index'])->name('index');
    Route::get('/mesas', [PosRestController::class, 'mesas'])->name('mesas');
    Route::get('/mesa/{mesa}', [PosRestController::class, 'mesa'])->name('mesa');
    Route::post('/mesa/{mesa}/pedido', [PosRestController::class, 'novoPedido'])->name('pedido.novo');
    Route::post('/pedido/{pedido}/item', [PosRestController::class, 'adicionarItem'])->name('pedido.item');
    Route::post('/pedido/{pedido}/items', [PosRestController::class, 'adicionarItems'])->name('pedido.items');
    Route::delete('/pedido/{pedido}/item/{item}', [PosRestController::class, 'removerItem'])->name('pedido.item.remover');
    Route::patch('/pedido/{pedido}/item/{item}/urgente', [PosRestController::class, 'toggleUrgente'])->name('pedido.item.urgente');
    Route::patch('/pedido/{pedido}/fechar', [PosRestController::class, 'fecharConta'])->name('pedido.fechar');
    Route::patch('/pedido/{pedido}/lugares', [PosRestController::class, 'atualizarLugares'])->name('pedido.lugares');
    Route::patch('/pedido/{pedido}/observacoes', [PosRestController::class, 'atualizarObservacoes'])->name('pedido.observacoes');
    Route::get('/pedido/{pedido}/talao', [PosRestController::class, 'talao'])->name('pedido.talao');
    Route::patch('/pedido/{pedido}/estado', [PosRestController::class, 'atualizarEstado'])->name('pedido.estado');
    Route::post('/mesa/{mesa}/extra', [PosRestController::class, 'pedidoExtra'])->name('pedido.extra');
    Route::get('/historico', [PosRestController::class, 'historico'])->name('historico');
    Route::patch('/reserva/{reserva}/associar', [PosRestController::class, 'associarReserva'])->name('reserva.associar');
});

Route::middleware('pos.auth')->prefix('pos-reservas')->name('pos.reservas.')->group(function () {
    Route::get('/', [PosReservasController::class, 'index'])->name('index');
    Route::post('/', [PosReservasController::class, 'store'])->name('store');
    Route::patch('/{reserva}', [PosReservasController::class, 'update'])->name('update');
    Route::patch('/{reserva}/chamar', [PosReservasController::class, 'chamar'])->name('chamar');
    Route::patch('/{reserva}/sentar', [PosReservasController::class, 'sentar'])->name('sentar');
    Route::patch('/{reserva}/cancelar', [PosReservasController::class, 'cancelar'])->name('cancelar');
    Route::delete('/{reserva}', [PosReservasController::class, 'destroy'])->name('destroy');
});

Route::middleware('pos.auth')->prefix('pos-cotas')->name('pos.cotas.')->group(function () {
    Route::get('/', [PosCotasController::class, 'index'])->name('index');
    Route::get('/socio/pesquisa', [PosCotasController::class, 'pesquisa'])->name('socio.pesquisa');
    Route::get('/socio/novo', [PosCotasController::class, 'novoSocioForm'])->name('socio.novo.form');
    Route::post('/socio/novo', [PosCotasController::class, 'novoSocio'])->name('socio.novo');
    Route::get('/socio/{socio}', [PosCotasController::class, 'socio'])->name('socio');
    Route::post('/socio/{socio}/pagar', [PosCotasController::class, 'registarPagamento'])->name('pagar');
    Route::get('/recibo/{cota}', [PosCotasController::class, 'recibo'])->name('recibo');
    Route::get('/em-atraso', [PosCotasController::class, 'emAtraso'])->name('em-atraso');
    Route::get('/resumo-dia', [PosCotasController::class, 'resumoDia'])->name('resumo-dia');
});

Route::get('/secao/bebidas', [SecaoController::class, 'bebidas'])->name('secao.bebidas');
Route::get('/secao/frango', [SecaoController::class, 'frango'])->name('secao.frango');
Route::get('/secao/comida', [SecaoController::class, 'comida'])->name('secao.comida');
Route::get('/secao/cozinha', [SecaoController::class, 'cozinha'])->name('secao.cozinha');
Route::get('/secao/sobremesas', [SecaoController::class, 'sobremesas'])->name('secao.sobremesas');
Route::get('/secao/acompanhamentos', [SecaoController::class, 'acompanhamentos'])->name('secao.acompanhamentos');
Route::get('/secao/servico', [SecaoController::class, 'servico'])->name('secao.servico');
Route::get('/secao/bar', [SecaoController::class, 'bar'])->name('secao.bar');
Route::get('/secao/sala/{codigo}', [SecaoController::class, 'sala'])->name('secao.sala');
Route::patch('/secao/items/{pedidoItem}/pronto', [SecaoController::class, 'pronto'])->name('secao.items.pronto');
Route::patch('/secao/pedidos/{pedido}/retirar', [SecaoController::class, 'retirar'])->name('secao.pedidos.retirar');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('socios', SocioController::class);
    Route::get('socios-em-atraso', [SocioController::class, 'emAtraso'])->name('socios.emAtraso');
    Route::get('exportar/socios-atraso', [SocioController::class, 'exportarPDF'])->name('socios.pdf');
    Route::resource('cotas', CotaController::class);
    Route::post('cotas/gerar', [CotaController::class, 'gerarCotas'])->name('cotas.gerar');
    Route::get('sala', [MesaController::class, 'sala'])->name('sala.index');
    Route::patch('mesas/mapa/guardar', [MesaController::class, 'guardarMapa'])->name('mesas.mapa.guardar');
    Route::patch('zonas/mapa/guardar', [\App\Http\Controllers\ZonaMapaController::class, 'guardarMapa'])->name('zonas.mapa.guardar');
    Route::post('zonas', [\App\Http\Controllers\ZonaMapaController::class, 'store'])->name('zonas.store');
    Route::patch('zonas/{zona}', [\App\Http\Controllers\ZonaMapaController::class, 'update'])->name('zonas.update');
    Route::delete('zonas/{zona}', [\App\Http\Controllers\ZonaMapaController::class, 'destroy'])->name('zonas.destroy');
    Route::post('mesas/{mesa}/dividir', [MesaController::class, 'dividir'])->name('mesas.dividir');
    Route::delete('mesas/{mesa}/submesas', [MesaController::class, 'juntar'])->name('mesas.juntar');
    Route::patch('mesas/{mesa}/libertar', [MesaController::class, 'libertar'])->name('mesas.libertar');
    Route::resource('mesas', MesaController::class);
    Route::patch('reservas/{reserva}/sentar', [ReservaController::class, 'sentar'])->name('reservas.sentar');
    Route::patch('reservas/{reserva}/chamar', [ReservaController::class, 'chamar'])->name('reservas.chamar');
    Route::resource('reservas', ReservaController::class);
    Route::get('eventos/{evento}/inscricoes', [EventoController::class, 'inscricoes'])->name('eventos.inscricoes');
    Route::delete('eventos/inscricoes/{inscricao}', [EventoController::class, 'destroyInscricao'])->name('eventos.inscricoes.destroy');
    Route::post('eventos/inscricoes/{inscricao}/confirmar', [EventoController::class, 'confirmarInscricao'])->name('eventos.inscricoes.confirmar');
    Route::resource('eventos', EventoController::class)->except(['create']);
    Route::post('eventos/{evento}/media', [EventoController::class, 'storeMedia'])->name('eventos.media.store');
    Route::post('eventos/{evento}/media-url', [EventoController::class, 'storeMediaUrl'])->name('eventos.media-url.store');
    Route::delete('eventos/media/{media}', [EventoController::class, 'destroyMedia'])->name('eventos.media.destroy');
    Route::resource('paginas', SitePageController::class)->only(['index', 'edit', 'update']);
    Route::resource('patrocinadores', SponsorAdminController::class)
        ->parameters(['patrocinadores' => 'patrocinadore'])
        ->except(['create', 'edit', 'show']);
    Route::post('patrocinadores/{patrocinadore}/imagens', [SponsorImageController::class, 'store'])->name('patrocinadores.imagens.store');
    Route::delete('patrocinadores/imagens/{imagem}', [SponsorImageController::class, 'destroy'])->name('patrocinadores.imagens.destroy');
    Route::get('manutencao/limpeza', [MaintenanceController::class, 'cleanup'])->name('manutencao.limpeza.index');
    Route::delete('manutencao/limpeza', [MaintenanceController::class, 'destroyCleanup'])->name('manutencao.limpeza.destroy');
    Route::get('manutencao/logs', [MaintenanceController::class, 'logs'])->name('manutencao.logs.index');
    // Alugueres do Salão
    Route::get('alugueres/opcoes', [AluguerController::class, 'opcoes'])->name('alugueres.opcoes');
    Route::post('alugueres/opcoes', [AluguerOpcaoController::class, 'store'])->name('alugueres.opcoes.store');
    Route::patch('alugueres/opcoes/{opcao}', [AluguerOpcaoController::class, 'update'])->name('alugueres.opcoes.update');
    Route::delete('alugueres/opcoes/{opcao}', [AluguerOpcaoController::class, 'destroy'])->name('alugueres.opcoes.destroy');
    Route::resource('alugueres', AluguerController::class)->only(['index', 'store', 'update', 'destroy']);

    // Contas bancárias da associação
    Route::get('contas-bancarias', [ContasBancariaController::class, 'index'])->name('contas-bancarias.index');
    Route::post('contas-bancarias', [ContasBancariaController::class, 'store'])->name('contas-bancarias.store');
    Route::post('contas-bancarias/saldo', [ContasBancariaController::class, 'atualizarSaldo'])->name('contas-bancarias.saldo');
    Route::patch('contas-bancarias/{contaBancaria}', [ContasBancariaController::class, 'update'])->name('contas-bancarias.update');
    Route::delete('contas-bancarias/{contaBancaria}', [ContasBancariaController::class, 'destroy'])->name('contas-bancarias.destroy');

    Route::get('relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
    Route::get('relatorios/periodo', [RelatorioController::class, 'porPeriodo'])->name('relatorios.periodo');
    Route::get('relatorios/exportar', [RelatorioController::class, 'exportarPDF'])->name('relatorios.pdf');
    Route::get('caixa', [CaixaDiariaController::class, 'index'])->name('caixa.index');
    Route::post('caixa', [CaixaDiariaController::class, 'store'])->name('caixa.store');
    Route::patch('caixa/{caixa}/fechar', [CaixaDiariaController::class, 'fechar'])->name('caixa.fechar');
    Route::get('bar', [BarController::class, 'index'])->name('bar.index');
    Route::get('bar/nova-conta', [BarController::class, 'novaContaBar'])->name('bar.nova-conta');
    Route::post('bar/nova-conta', [BarController::class, 'storeContaBar'])->name('bar.store-conta');
    Route::get('bar/prepago', [BarController::class, 'novoPrepago'])->name('bar.prepago');
    Route::post('bar/prepago', [BarController::class, 'storePrepago'])->name('bar.store-prepago');
    Route::get('bar/{pedido}/talao', [BarController::class, 'talao'])->name('bar.talao');
    Route::get('bar/{pedido}', [BarController::class, 'show'])->name('bar.show');
    Route::patch('bar/{pedido}/fechar', [BarController::class, 'fecharContaBar'])->name('bar.fechar');
    Route::get('pedidos/{pedido}/talao', [PedidoController::class, 'talao'])->name('pedidos.talao');
    Route::patch('pedidos/{pedido}/fechar-conta', [PedidoController::class, 'fecharConta'])->name('pedidos.fecharConta');
    Route::resource('pedidos', PedidoController::class);
    Route::patch('pedidos/{pedido}/estado', [PedidoController::class, 'atualizarEstado'])->name('pedidos.estado');
    Route::resource('pedido-items', PedidoItemController::class)->parameters(['pedido-items' => 'pedidoItem'])->except(['index', 'create', 'show', 'edit']);
    Route::resource('produtos', ProdutoController::class)->except(['create', 'show', 'edit']);
    Route::get('faturas-compras', [FaturaCompraController::class, 'index'])->name('faturas-compras.index');
    Route::post('faturas-compras', [FaturaCompraController::class, 'store'])->name('faturas-compras.store');
    Route::patch('faturas-compras/{fatura}/pagar', [FaturaCompraController::class, 'pagar'])->name('faturas-compras.pagar');
    Route::post('faturas-compras/{fatura}/devolver', [FaturaCompraController::class, 'devolver'])->name('faturas-compras.devolver');
    Route::get('valor-extras', [ValorExtraController::class, 'index'])->name('valor-extras.index');
    Route::post('valor-extras', [ValorExtraController::class, 'store'])->name('valor-extras.store');
    Route::delete('valor-extras/{valorExtra}', [ValorExtraController::class, 'destroy'])->name('valor-extras.destroy');
    Route::get('contas-festa', [FestaContaController::class, 'index'])->name('contas-festa.index');
    Route::post('contas-festa', [FestaContaController::class, 'store'])->name('contas-festa.store');
    Route::put('contas-festa/{contasFesta}', [FestaContaController::class, 'update'])->name('contas-festa.update');
    Route::delete('contas-festa/{contasFesta}', [FestaContaController::class, 'destroy'])->name('contas-festa.destroy');
    Route::get('impressoras/download-agente', [ImpressoraController::class, 'downloadAgente'])->name('impressoras.download-agente');
    Route::post('impressoras/retentar-falhados', [ImpressoraController::class, 'retentarFalhados'])->name('impressoras.retentar-falhados');
    Route::get('impressoras/status-jobs', [ImpressoraController::class, 'statusJobs'])->name('impressoras.status-jobs');
    Route::get('impressoras', [ImpressoraController::class, 'index'])->name('impressoras.index');
    Route::post('impressoras', [ImpressoraController::class, 'store'])->name('impressoras.store');
    Route::patch('impressoras/{impressora}', [ImpressoraController::class, 'update'])->name('impressoras.update');
    Route::delete('impressoras/{impressora}', [ImpressoraController::class, 'destroy'])->name('impressoras.destroy');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/pos', [UserController::class, 'storePos'])->name('users.pos.store');
    Route::patch('users/pos/{pos}', [UserController::class, 'updatePos'])->name('users.pos.update');
    Route::delete('users/pos/{pos}', [UserController::class, 'destroyPos'])->name('users.pos.destroy');

    // Comissão de Festas — back-office
    Route::get('comissao/pendentes', [ChamadaComissaoController::class, 'pendentes'])->name('comissao.pendentes');
    Route::post('comissao/{chamada}/atender', [ChamadaComissaoController::class, 'atender'])->name('comissao.atender');
    Route::get('pos-painel', [PosPainelController::class, 'index'])->name('pos-painel.index');
    Route::post('pos-painel/pin', [PosPainelController::class, 'atualizarPin'])->name('pos-painel.pin');
});

require __DIR__.'/auth.php';