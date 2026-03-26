import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                fleet: {
                    page: '#f8fafc',
                    sidebar: '#f3f4f6',
                    card: '#ffffff',
                    ink: '#0f172a',
                    secondary: '#475569',
                    muted: '#64748b',
                    border: '#e2e8f0',
                    primary: '#2563eb',
                    success: '#10b981',
                    profit: '#1e5128',
                    danger: '#dc2626',
                    dark: '#111827',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
