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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // AQUI ESTÁ A NOSSA NOVA PALETA DE CORES
            colors: {
                'primary': '#313131ff',   // Fundo principal mais escuro
                'secondary': '#444444', // Fundo de elementos secundários (colunas, modais)
                'accent': '#B0B0B0',    // Cor de destaque para bordas, ícones
                'text-primary': '#858585ff', // Cor de texto principal (claro)
                'text-secondary': '#888888', // Cor de texto secundário (cinza mais claro)
            },
        },
    },

    plugins: [forms],
};
