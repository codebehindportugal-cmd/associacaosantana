<?php

use App\Http\Controllers\BarController;
use App\Http\Controllers\CaixaDiariaController;
use App\Http\Controllers\ClientePedidoController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpressoraController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoItemController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PosBarController;
use App\Http\Controllers\PosCotasController;
use App\Http\Controllers\PosLoginController;
use App\Http\Controllers\PosRestController;
use App\Http\Controllers\PrecarioController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\SecaoController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\SponsorAdminController;
use App\Http\Controllers\SponsorScreenController;
use App\Http\Controllers\SponsorshipController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sobre-nos', [PagesController::class, 'sobreNos'])->name('pages.sobre-nos');
Route::get('/patrocinios', [SponsorshipController::class, 'index'])->name('patrocinios.index');
Route::post('/patrocinios', [SponsorshipController::class, 'store'])->name('patrocinios.store');
Route::get('/ecra-patrocinios', SponsorScreenController::class)->name('patrocinios.ecra');
Route::get('/politica-de-privacidade', [LegalController::class, 'privacidade'])->name('legal.privacidade');
Route::get('/termos-e-condicoes', [LegalController::class, 'termos'])->name('legal.termos');
Route::get('/politica-de-cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/precario', PrecarioController::class)->name('precario');
Route::post('/contacto', ContactController::class)->name('contacto.store');
Route::get('/evento/{evento}', [EventoController::class, 'publicShow'])->name('eventos.public.show');
Route::get('/cliente/{token}', [ClientePedidoController::class, 'show'])->name('cliente.mesa');
Route::post('/cliente/{token}/item', [ClientePedidoController::class, 'addItem'])->name('cliente.item');
Route::post('/cliente/{token}/items', [ClientePedidoController::class, 'addItems'])->name('cliente.items');
Route::get('/cliente/{token}/confirmacao', [ClientePedidoController::class, 'confirmacao'])->name('cliente.confirmacao');

Route::get('/pos/login', [PosLoginController::class, 'show'])->name('pos.login');
Route::post('/pos/login', [PosLoginController::class, 'store'])->name('pos.login.store');
Route::post('/pos/logout', [PosLoginController::class, 'destroy'])->name('pos.logout');

Route::middleware('pos.auth')->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosBarController::class, 'index'])->name('index');
    Route::post('/prepago', [PosBarController::class, 'storePrepago'])->name('prepago.store');
    Route::get('/pedido/{pedido}/talao', [PosBarController::class, 'talao'])->name('pedido.talao');
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
    Route::get('/pedido/{pedido}/talao', [PosRestController::class, 'talao'])->name('pedido.talao');
    Route::patch('/pedido/{pedido}/estado', [PosRestController::class, 'atualizarEstado'])->name('pedido.estado');
    Route::get('/historico', [PosRestController::class, 'historico'])->name('historico');
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
    Route::resource('reservas', ReservaController::class);
    Route::resource('eventos', EventoController::class)->except(['create']);
    Route::post('eventos/{evento}/media', [EventoController::class, 'storeMedia'])->name('eventos.media.store');
    Route::delete('eventos/media/{media}', [EventoController::class, 'destroyMedia'])->name('eventos.media.destroy');
    Route::resource('paginas', SitePageController::class)->only(['index', 'edit', 'update']);
    Route::resource('patrocinadores', SponsorAdminController::class)
        ->parameters(['patrocinadores' => 'patrocinadore'])
        ->except(['create', 'edit', 'show']);
    Route::get('manutencao/limpeza', [MaintenanceController::class, 'cleanup'])->name('manutencao.limpeza.index');
    Route::delete('manutencao/limpeza', [MaintenanceController::class, 'destroyCleanup'])->name('manutencao.limpeza.destroy');
    Route::get('manutencao/logs', [MaintenanceController::class, 'logs'])->name('manutencao.logs.index');
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
    Route::resource('impressoras', ImpressoraController::class)->except(['create', 'show', 'edit']);
    Route::resource('users', UserController::class)->except(['create', 'show']);
    Route::post('users/pos', [UserController::class, 'storePos'])->name('users.pos.store');
    Route::put('users/pos/{pos}', [UserController::class, 'updatePos'])->name('users.pos.update');
    Route::delete('users/pos/{pos}', [UserController::class, 'destroyPos'])->name('users.pos.destroy');
});

require __DIR__.'/auth.php';
