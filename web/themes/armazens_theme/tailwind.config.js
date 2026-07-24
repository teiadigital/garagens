/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./templates/**/*.twig",
    "./css/style.css",
    "./*.theme",
    "./inc/**/*.inc",
    "../../modules/custom/**/*.twig",
    "../../modules/custom/**/*.php",
  ],
  corePlugins: {
    preflight: false,
  },
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
        "black-text": "#171717",
        gray: "#E8E6E1",
        "gray-light": "#F6F5F2",
        "gray-dark": "#252525",
        "blue-light": "#D8D5CE",
        "blue-dark": "#F6F5F2",
        "blue-background": "#171717",
        "green-custom": "#2F5665",
        "orange-custom": "#F9A902",
      },
    },
  },
};
