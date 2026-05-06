import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta inspirada no Trello / Moderna
                trello: {
                    blue: '#0079bf',
                    green: '#51e898',
                    orange: '#ff9f1a',
                    red: '#eb5a46',
                    yellow: '#f2d600',
                    purple: '#c377e0',
                    pink: '#ff78cb',
                    sky: '#00c2e0',
                    lime: '#51e898',
                },
                // Cores dinâmicas usando <alpha-value> para suportar modificadores de opacidade (ex: bg-brand/10)
                'surface': 'rgb(var(--color-surface) / <alpha-value>)',
                'surface-variant': 'rgb(var(--color-surface-variant) / <alpha-value>)',
                'surface-hover': 'rgb(var(--color-surface-hover) / <alpha-value>)',
                'brand': 'rgb(var(--color-brand) / <alpha-value>)',
                'text-main': 'rgb(var(--color-text-main) / <alpha-value>)',
                'text-muted': 'rgb(var(--color-text-muted) / <alpha-value>)',
                'text-subtle': 'rgb(var(--color-text-subtle) / <alpha-value>)',
                'border-main': 'rgb(var(--color-border-main) / <alpha-value>)',
                'border-subtle': 'rgb(var(--color-border-subtle) / <alpha-value>)',
            },
            boxShadow: {
                'card': '0 1px 0 rgba(9, 30, 66, 0.25)',
                'card-hover': '0 8px 16px -2px rgba(0, 0, 0, 0.15), 0 0 1px rgba(9, 30, 66, 0.31)',
                'sidebar': '2px 0 12px rgba(0, 0, 0, 0.1)',
                'lg-dark': '0 20px 25px -5px rgba(0, 0, 0, 0.3)',
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out',
                'slide-up': 'slideUp 0.4s ease-out',
                'slide-in-left': 'slideInLeft 0.3s ease-out',
                'slide-out-left': 'slideOutLeft 0.3s ease-in',
                'bounce-in': 'bounceIn 0.5s ease-out',
                'pulse-subtle': 'pulseSubtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                slideInLeft: {
                    '0%': { transform: 'translateX(-100%)', opacity: '0' },
                    '100%': { transform: 'translateX(0)', opacity: '1' },
                },
                slideOutLeft: {
                    '0%': { transform: 'translateX(0)', opacity: '1' },
                    '100%': { transform: 'translateX(-100%)', opacity: '0' },
                },
                bounceIn: {
                    '0%': { transform: 'scale(0.9)', opacity: '0' },
                    '50%': { transform: 'scale(1.05)' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                pulseSubtle: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '.8' },
                },
            },
            spacing: {
                'sidebar-width': '280px',
                'sidebar-collapsed': '80px',
            },
        },
    },

    plugins: [forms],
};
