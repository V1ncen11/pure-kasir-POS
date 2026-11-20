import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50:  '#f2f5f8',
                    100: '#e7ecf1',
                    200: '#d3dce4',
                    300: '#bac7d3',
                    400: '#91a3b6',
                    500: '#7187a1',
                    600: '#526d8a',
                    700: '#435a74',
                    800: '#36495e',
                    900: '#2a394b',
                },
            },
            boxShadow: {
                card:    '0 1px 3px 0 rgb(0 0 0 / 0.08), 0 1px 2px -1px rgb(0 0 0 / 0.08)',
                dropdown:'0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
                modal:   '0 25px 50px -12px rgb(0 0 0 / 0.25)',
            },
        },
    },
    plugins: [forms],
};
