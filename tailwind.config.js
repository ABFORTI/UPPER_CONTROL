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
                sans: ['Roboto', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Arial'],
                display: ['Poppins', 'ui-sans-serif', 'system-ui'],
            },
            colors: {
                brand: {
                    primary: '#1A73E8',
                    primaryDark: '#1558b0',
                    success: '#34A853',
                    danger: '#EA4335',
                    text: '#2E3A59',
                    surface: '#F5F7FA',
                    // Paleta Upper Logistics
                    upper: {
                        50: '#F3F4FF',
                        100: '#E6E6FF',
                        200: '#C9C8FF',
                        300: '#ABABFF',
                        400: '#8E8AFF',
                        500: '#1E1C8F', // principal
                        600: '#19176F',
                        700: '#14134F',
                        800: '#0F0F30',
                    },
                    accent: {
                        orange: '#FF7A00',
                        lime: '#7ED321',
                    },
                },
            },
            boxShadow: {
                card: '0 8px 24px rgba(20, 30, 66, 0.08)',
            },
        },
    },

    plugins: [forms],
};
