/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}",
    "./public/index.html"
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50:  '#fdf6f0',
          100: '#fae8d8',
          200: '#f4ccaa',
          300: '#ecaa75',
          400: '#e28040',
          500: '#8B4513',
          600: '#7a3c10',
          700: '#65320d',
          800: '#522a0b',
          900: '#422208',
          DEFAULT: '#8B4513',
        },
        secondary: {
          50:  '#fdfcfa',
          100: '#faf7f2',
          200: '#f5f0e8',
          300: '#ede4d4',
          400: '#e0d2b8',
          500: '#cebda0',
          600: '#b5a080',
          700: '#967f5e',
          800: '#766144',
          900: '#5a4933',
          DEFAULT: '#F5F0E8',
        },
        accent: {
          50:  '#f4f6ee',
          100: '#e5ebd5',
          200: '#ccd8ae',
          300: '#aabe7f',
          400: '#89a455',
          500: '#6B7C3F',
          600: '#5a6a35',
          700: '#4a572b',
          800: '#3b4422',
          900: '#2e3419',
          DEFAULT: '#6B7C3F',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        serif: ['Georgia', 'Cambria', 'serif'],
      },
      boxShadow: {
        'craft': '0 4px 6px -1px rgba(139, 69, 19, 0.1), 0 2px 4px -1px rgba(139, 69, 19, 0.06)',
        'craft-lg': '0 10px 15px -3px rgba(139, 69, 19, 0.15), 0 4px 6px -2px rgba(139, 69, 19, 0.08)',
      },
    },
  },
  plugins: [],
};
