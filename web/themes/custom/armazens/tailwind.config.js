/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "**/*.twig",
    "*.theme",
    "inc/**/*.inc",
    "./node_modules/flowbite/**/*.js",
    "../../../{modules,themes}/custom/**/*.twig",
  ],
  theme: {
    fontFamily: {
      Montserrat: ["Montserrat", "sans-serif"],
    },
    extend: {
      colors: {
        white: "#ffffff",
        transparent: "transparent",
        black: "#000000",
        gray: "#EBEBEB",
        "gray-light": "#F0F2F7",
        "gray-dark": "#5B5959",
        red: "#D80027",
        green: "#6DA544",
        yellow: "#FFDA44",
        "blue-light": "#C5D1E2",
      },
    },
  },
  plugins: [require("@tailwindcss/forms")],
};
