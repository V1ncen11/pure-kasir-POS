<script setup>
defineProps({
  kind: {
    type: String,
    required: true,
  },
})
</script>

<template>
  <svg
    viewBox="0 0 48 48"
    class="dashboard-stat-illustration"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <template v-if="kind === 'sales'">
      <path d="M9 5.5h20l8 8V39a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V8.5a3 3 0 0 1 3-3Z" fill="#43A047" />
      <path d="M29 5.5v8h8" fill="#A5D6A7" />
      <path d="M12 18h19M12 23h19M12 28h19M12 33h14M17 17v18M23 17v18M29 17v18" stroke="#E8F5E9" stroke-width="1.5" />
      <path d="m27 35 12-12 4 4-12 12-5 1 1-5Z" fill="#F4C542" stroke="#9A6A18" stroke-width="1.5" stroke-linejoin="round" />
      <path d="m39 23 2-2a2 2 0 0 1 3 0l1 1a2 2 0 0 1 0 3l-2 2-4-4Z" fill="#E85D4A" />
    </template>

    <template v-else-if="kind === 'transactions'">
      <path d="M10 5h19l9 9v23a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4V9a4 4 0 0 1 4-4Z" fill="#4B83C3" />
      <path d="M29 5v9h9" fill="#A9C9EB" />
      <path d="M13 20h13M13 25h11M13 30h8" stroke="#EAF3FC" stroke-width="2" stroke-linecap="round" />
      <ellipse cx="34" cy="34" rx="8" ry="4" fill="#F4C542" />
      <path d="M26 30v7c0 2.2 3.6 4 8 4s8-1.8 8-4v-7" fill="#E9AA27" />
      <ellipse cx="34" cy="30" rx="8" ry="4" fill="#FFD65A" stroke="#D89518" stroke-width="1.5" />
      <path d="M29 34c1.2 1 2.8 1.5 5 1.5s3.8-.5 5-1.5" stroke="#D89518" stroke-width="1.2" />
    </template>

    <template v-else-if="kind === 'revenue'">
      <path d="M7 39V11a3 3 0 0 1 3-3h19v31H10a3 3 0 0 1-3-3Z" fill="#4AA64F" />
      <path d="M12 14h12M12 19h12M12 24h12M12 29h12M12 34h12M16 13v22M21 13v22" stroke="#EAF6E8" stroke-width="1.4" />
      <path d="M28 18h13a2 2 0 0 1 2 2v20H27V20a2 2 0 0 1 1-2Z" fill="#3C4858" />
      <rect x="30" y="21" width="9" height="4" rx="1" fill="#B9D5EF" />
      <path d="M31 29h2m4 0h2m-8 4h2m4 0h2m-8 4h2m4 0h2" stroke="#F4C542" stroke-width="2" stroke-linecap="round" />
    </template>

    <template v-else-if="kind === 'discount'">
      <path d="m8 10 15-5 19 19-13 19L8 23V10Z" fill="#F4C542" stroke="#C88B1C" stroke-width="1.8" stroke-linejoin="round" />
      <circle cx="16" cy="14" r="2.4" fill="#FFF7DC" />
      <path d="m19 32 11-12m-9 1h.1M29 31h.1" stroke="#9A6411" stroke-width="2.4" stroke-linecap="round" />
      <circle cx="21" cy="21" r="1.8" fill="#9A6411" />
      <circle cx="29" cy="31" r="1.8" fill="#9A6411" />
    </template>

    <template v-else-if="kind === 'average'">
      <path d="M12 5h22a3 3 0 0 1 3 3v31l-4-2.5-4 2.5-4-2.5-4 2.5-4-2.5-4 2.5V8a3 3 0 0 1 3-3Z" fill="#4B83C3" />
      <path d="M17 12h12M17 17h8" stroke="#EAF3FC" stroke-width="2" stroke-linecap="round" />
      <circle cx="24" cy="29" r="7" fill="#FFD65A" stroke="#D89518" stroke-width="1.5" />
      <path d="M24 24v10m-2.5-7h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4" stroke="#9A6411" stroke-width="1.5" stroke-linecap="round" />
    </template>

    <template v-else>
      <path d="m6 17 18-10 18 10-18 10L6 17Z" fill="#F4C542" stroke="#C88B1C" stroke-width="1.5" stroke-linejoin="round" />
      <path d="M6 17v18l18 10V27L6 17Z" fill="#E7A52C" stroke="#C88B1C" stroke-width="1.5" stroke-linejoin="round" />
      <path d="m42 17v18L24 45V27l18-10Z" fill="#F7C64B" stroke="#C88B1C" stroke-width="1.5" stroke-linejoin="round" />
      <path d="m16 11 18 10M24 27v18" stroke="#FFF2C5" stroke-width="1.5" />
    </template>
  </svg>
</template>

<style scoped>
.dashboard-stat-illustration {
  display: block;
  width: 40px;
  height: 40px;
  overflow: visible;
}
</style>
