import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Archivo', ...defaultTheme.fontFamily.sans],
                display: ['Archivo', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            // Sistema visual do redesign (ver docs/REDESIGN.md)
            colors: {
                tinta: '#16201C',
                fundo: '#F4F5F2',
                suave: { DEFAULT: '#4A5751', 2: '#5D6A64' },
                linha: { DEFAULT: '#DDE1DB', forte: '#D5D9D3', fraca: '#ECEEEA' },
                verde: { DEFAULT: '#0F6B4F', escuro: '#0A4D39', claro: '#E6F2EC', claro2: '#D7EDE2', ok: '#1E9E6A' },
                laranja: { DEFAULT: '#C2570C', claro: '#FFF4EA', texto: '#8A3B06' },
                perigo: { DEFAULT: '#A3241A', claro: '#FBEAE7', texto: '#8A2A1E' },
                escuro: { DEFAULT: '#16201C', 2: '#2B3833', inativo: '#D6DED9' },
                azul: { DEFAULT: '#2A5BC0' },
                roxo: { DEFAULT: '#7A3FA0' },
                secao: { grelhados: '#B4530F', cozinha: '#0F6B4F', bar: '#2A5BC0', sobremesas: '#7A3FA0', acompanhamentos: '#6B6A12', servico: '#4A5751' },
            },
        },
    },

    plugins: [forms],
};
