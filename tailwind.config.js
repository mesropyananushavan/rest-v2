export default {
    content: [
        './app/**/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                smartrest: {
                    bg: '#f1f2f7',
                    surface: '#ffffff',
                    text: '#2e2e2e',
                    muted: '#6c757d',
                    border: '#eeeeee',
                    success: '#5cb85c',
                    danger: '#d43f3a',
                    warning: '#f5b400',
                    info: '#5bc0de',
                    sidebar: '#141821',
                    ink: '#17202b',
                    table: {
                        hover: '#f8f8f8',
                        open: '#5cb85c',
                        warning: '#f5b400',
                        danger: '#d43f3a',
                    },
                },
            },
            borderRadius: {
                'sr-card': '9px',
                'sr-control': '4px',
                'sr-brand': '0.85rem',
                'sr-panel': '1.2rem',
            },
            boxShadow: {
                'sr-card': '0 1px 3px rgb(0 0 0 / 12%)',
                'sr-sidebar': '8px 0 30px rgb(20 24 33 / 12%)',
            },
            spacing: {
                'sr-header': '60px',
                'sr-sidebar': '274px',
                'sr-logo': '219px',
            },
            backdropBlur: {
                sr: '18px',
            },
        },
    },
};
