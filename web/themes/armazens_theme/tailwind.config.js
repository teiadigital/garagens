/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "**/*.twig",
    "css/style.css",
    "*.theme",
    "inc/**/*.inc",
    "../../{modules,themes}/custom/**/*.twig",
  ],
  theme: {
    container: {
      center: true,
      screens: {
        sm: "640px",
        md: "768px",
        lg: "1024px",
        xl: "1280px",
      },
    },
    screens: {
      sm: "640px",
      md: "768px",
      lg: "1024px",
      xl: "1280px",
      "2xl": "1640px",
    },
    fontFamily: {
      Montserrat: ["Montserrat", "sans-serif"],
    },
    extend: {
      colors: {
        white: "#ffffff",
        transparent: "transparent",
        black: "#000000",
        "black-text": "#1C1C1C",
        gray: "#EBEBEB",
        "gray-light": "#F0F2F7",
        "gray-dark": "#5B5959",
        "blue-light": "#C5D1E2",
        "blue-dark": "#D7DBE7",
        "blue-background": "#041C37",
        "green-custom": "#6F9B3A",
        "orange-custom": "#F33C12",
      },
    },
  },
};
