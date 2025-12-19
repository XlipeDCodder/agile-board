<script setup>
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

defineProps({
  data: {
    type: Object,
    required: true
  },
  options: {
    type: Object,
    default: () => ({
        responsive: true,
        maintainAspectRatio: false
    })
  }
})
</script>

<template>
  <Pie :data="data" :options="options" />
</template>
