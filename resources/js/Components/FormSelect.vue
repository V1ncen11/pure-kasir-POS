<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Pilih opsi' },
})

const emit = defineEmits(['update:modelValue'])
const root = ref(null)
const isOpen = ref(false)
const activeIndex = ref(0)
const listboxId = `form-select-${Math.random().toString(36).slice(2)}`

const normalizedOptions = computed(() => props.options.map((option) => {
  if (typeof option === 'object' && option !== null) {
    return {
      value: option.value ?? option.id,
      label: option.label ?? option.name ?? String(option.value ?? option.id ?? ''),
    }
  }

  return { value: option, label: String(option) }
}))

const selectedOption = computed(() => normalizedOptions.value.find(
  (option) => String(option.value) === String(props.modelValue),
))

function openMenu() {
  activeIndex.value = Math.max(0, normalizedOptions.value.findIndex(
    (option) => String(option.value) === String(props.modelValue),
  ))
  isOpen.value = true
}

function choose(option) {
  emit('update:modelValue', option.value)
  isOpen.value = false
}

function handleKeydown(event) {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    if (!isOpen.value) {
      openMenu()
      return
    }

    const direction = event.key === 'ArrowDown' ? 1 : -1
    const count = normalizedOptions.value.length
    if (count) activeIndex.value = (activeIndex.value + direction + count) % count
  } else if ((event.key === 'Enter' || event.key === ' ') && isOpen.value) {
    event.preventDefault()
    const option = normalizedOptions.value[activeIndex.value]
    if (option) choose(option)
  } else if (event.key === 'Escape' && isOpen.value) {
    event.preventDefault()
    isOpen.value = false
  }
}

function closeOnOutsideClick(event) {
  if (root.value && !root.value.contains(event.target)) isOpen.value = false
}

onMounted(() => window.addEventListener('pointerdown', closeOnOutsideClick))
onBeforeUnmount(() => window.removeEventListener('pointerdown', closeOnOutsideClick))
</script>

<template>
  <div ref="root" class="relative w-full">
    <button
      type="button"
      role="combobox"
      aria-haspopup="listbox"
      :aria-expanded="isOpen"
      :aria-controls="listboxId"
      class="form-select-trigger"
      :class="{ 'form-select-trigger--open': isOpen }"
      @click="isOpen ? (isOpen = false) : openMenu()"
      @keydown="handleKeydown"
    >
      <span :class="selectedOption ? 'text-slate-900' : 'text-slate-400'">
        {{ selectedOption?.label ?? placeholder }}
      </span>
      <svg
        class="form-select-chevron"
        :class="{ 'form-select-chevron--open': isOpen }"
        viewBox="0 0 20 20"
        fill="none"
        aria-hidden="true"
      >
        <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <Transition name="select-menu">
      <div v-if="isOpen" :id="listboxId" role="listbox" class="form-select-menu">
        <button
          v-for="(option, index) in normalizedOptions"
          :key="String(option.value)"
          type="button"
          role="option"
          :aria-selected="String(option.value) === String(modelValue)"
          class="form-select-option"
          :class="{
            'form-select-option--active': index === activeIndex,
            'form-select-option--selected': String(option.value) === String(modelValue),
          }"
          @mouseenter="activeIndex = index"
          @click="choose(option)"
        >
          <span>{{ option.label }}</span>
          <svg
            v-if="String(option.value) === String(modelValue)"
            class="h-4 w-4 text-primary-600"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          >
            <path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <div v-if="!normalizedOptions.length" class="px-3 py-2 text-sm text-slate-400">
          Belum ada pilihan
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.form-select-trigger {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font-size: 0.875rem;
  line-height: 1.25rem;
  text-align: left;
  transition: border-color 150ms, box-shadow 150ms;
}
.form-select-trigger:hover { border-color: #94a3b8; }
.form-select-trigger:focus-visible,
.form-select-trigger--open {
  border-color: #526d8a;
  outline: none;
  box-shadow: 0 0 0 2px rgb(59 130 246 / 18%);
}
.form-select-chevron {
  width: 18px;
  height: 18px;
  flex: none;
  color: #64748b;
  transition: transform 150ms;
}
.form-select-chevron--open { transform: rotate(180deg); }
.form-select-menu {
  position: absolute;
  z-index: 50;
  top: calc(100% + 6px);
  left: 0;
  width: 100%;
  max-height: 256px;
  overflow-y: auto;
  padding: 4px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 12px 28px rgb(15 23 42 / 14%), 0 2px 6px rgb(15 23 42 / 6%);
}
.form-select-option {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 9px 10px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #334155;
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
  transition: background-color 120ms, color 120ms;
}
.form-select-option:hover,
.form-select-option--active { background: #eff6ff; color: #435a74; }
.form-select-option--selected { font-weight: 600; }
.select-menu-enter-active,
.select-menu-leave-active { transition: opacity 120ms ease, transform 120ms ease; }
.select-menu-enter-from,
.select-menu-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
