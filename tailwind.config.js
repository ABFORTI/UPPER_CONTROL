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
                },
            },
            boxShadow: {
                card: '0 8px 24px rgba(20, 30, 66, 0.08)',
            },
        },
    },

    plugins: [forms],
};
