// Compara os taloes montados no browser (resources/js/pos/taloes.js) com os do
// servidor. Le os casos em JSON do stdin e escreve "OK" ou as diferencas.
// Usado por tests/Feature/Pos/TaloesParidadeTest.php.
import { readFileSync } from 'node:fs';
import { isDeepStrictEqual } from 'node:util';
import * as taloes from '../../resources/js/pos/taloes.js';

const casos = JSON.parse(readFileSync(0, 'utf8'));
const erros = [];

for (const caso of casos) {
    const { venda, config, hora } = caso;
    const obtido = {
        venda: () => taloes.taloesDaVenda(venda, config, hora),
        anulacao: () => [taloes.payloadAnulacao(venda, config, caso.devolver, caso.anulacao)],
        caucao: () => [taloes.payloadCaucao(config, caso.caucao)],
        segunda_via: () => taloes.taloesDaVenda(venda, config, hora).map(taloes.marcarSegundaVia),
    }[caso.tipo ?? 'venda']();

    // A gaveta e decidida a parte (abre no primeiro talao das vendas a dinheiro)
    const semGaveta = (lista) => lista.map((p) => { const c = { ...p }; if (caso.ignorar_gaveta) delete c.abrir_caixa; return c; });
    const esperado = semGaveta(caso.esperado);
    if (!isDeepStrictEqual(semGaveta(JSON.parse(JSON.stringify(obtido))), esperado)) {
        erros.push(`Caso "${caso.nome}":\n  servidor: ${JSON.stringify(esperado)}\n  browser:  ${JSON.stringify(obtido)}`);
    }
}

console.log(erros.length ? erros.join('\n\n') : 'OK');
