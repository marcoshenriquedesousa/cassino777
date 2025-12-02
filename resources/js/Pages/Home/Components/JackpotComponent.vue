<template>
  <div class="jackpot-wrapper">
    <!-- Jackpot Um -->
    <div class="jackpot-container">
      <img src="/public/assets/images/jackpot3.avif" alt="Jackpot" class="jackpot-image">
      <div class="jackpot-value">
        {{ formatNumber(currentJackpotValue.toFixed(2)) }}
      </div>
    </div>
  </div>
</template>


<script>
import { useSettingStore } from "@/Stores/SettingStore.js";
import { useRouter } from "vue-router";

export default {
  data() {
    return {
      isLoading: false,
      setting: null,
      custom: {},
      jackpotValue: 714183, // valor inicial do jackpot
      currentJackpotValue: 714981, // valor exibido do jackpot
    };
  },
  setup() {
    const router = useRouter();
    return { router };
  },
  computed: {
    isJackpotOneActive() {
      return !!this.custom?.bt_1_link || !!this.custom?.bt_2_link || !!this.custom?.bt_3_link || !!this.custom?.bt_4_link;
    }
  },
  methods: {
    getSetting() {
      const settingStore = useSettingStore();
      if (settingStore && settingStore.setting && settingStore?.custom) {
        this.setting = settingStore?.setting || {};
        this.custom = settingStore?.custom || {}; // Certifique-se de que custom está definido
      }
    },
    incrementJackpot() {
      this.jackpotValue *= 1.0001; // aumenta 0,01%
      this.animateJackpot();
    },
    animateJackpot() {
      const duration = 1000; // duração da animação em milissegundos
      const startValue = this.currentJackpotValue;
      const endValue = this.jackpotValue;
      const startTime = performance.now();

      const animate = (currentTime) => {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1);
        this.currentJackpotValue = startValue + (endValue - startValue) * progress;
        if (progress < 1) {
          requestAnimationFrame(animate);
        }
      };

      requestAnimationFrame(animate);
    },
    formatNumber(number) {
      return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
  },
  created() {
    setInterval(this.incrementJackpot, 3000); // chama incrementJackpot a cada 2 segundos
    this.getSetting();
  }
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Shrikhand&display=swap');

.jackpot-wrapper {
  padding-top: 20px;
  background-color: var(--background-color-jackpot);
}

.jackpot-container {
  position: relative;
  width: 100%;
  margin: 0 auto;
  aspect-ratio: 2.5; /* Mantém a proporção da imagem */
}

.jackpot-image {
  width: 100%;
  height: auto;
  object-fit: contain; /* Garante que a imagem seja renderizada corretamente */
  position: absolute;
  top: 4%;
}

.jackpot-value {
  position: absolute;
  top: 30%; /* Ajuste vertical */
  left: 50%; /* Centraliza horizontalmente */
  transform: translate(-50%, 0); /* Garante centralização exata */
  font-size: 30px; /* Tamanho mínimo de 1rem, preferencial de 2vw, máximo de 1.5rem */
  color: var(--value-color-jackpot, #ffffff);
  font-family: 'Shrikhand', cursive; /* Aplica a fonte Shrikhand */
  letter-spacing: 0.15em;
  text-shadow: 
    -1px -1px 0 #FF0000, 
    1px -1px 0 #FF0000, 
    -1px 1px 0 #FF0000, 
    1px 1px 0 #FF0000; /* Contorno vermelho */
  text-align: center;
  white-space: nowrap; /* Evita quebra de linha */
}

@media screen and (max-width: 667px) {
  .jackpot-value {
    font-size: 25px;
  }
}
</style>

