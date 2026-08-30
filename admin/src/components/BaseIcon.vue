<script setup>
/**
 * أيقونات SVG مضمّنة.
 *
 * لا Font Awesome هنا: اللوحة تحتاج ستّ عشرة أيقونة، وتحميل خط أيقونات
 * بحجم 370KB لأجلها إسراف. هذه أخفّ من 3KB ولا تحتاج طلب شبكة.
 */
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
})

const paths = {
  gauge: 'M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0-9a9 9 0 0 1 9 9M3 14a9 9 0 0 1 9-9m1.5 7.5 4-4',
  children: 'M9 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm6 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM7 21v-6l-2-2 1.5-3h5L13 13l-2 2v6M17 21v-5l-2-2 1-3h4',
  home: 'M3 10.5 12 3l9 7.5M5 9.5V20h14V9.5M10 20v-6h4v6',
  users: 'M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9M16 2.1a4 4 0 0 1 0 7.8',
  handshake: 'm11 17 2 2a1 1 0 1 0 3-3m-5 1 3.5 3.5a1 1 0 1 0 3-3l-3.6-3.6M6 10 3 7l5-5 3 3h3l4 4-3.5 3.5a1 1 0 0 1-1.5 0L11 9l-5 5a1 1 0 0 1-1.5 0L3 12.5',
  receipt: 'M4 2v20l2-1.5L8 22l2-1.5L12 22l2-1.5L16 22l2-1.5L20 22V2l-2 1.5L16 2l-2 1.5L12 2l-2 1.5L8 2 6 3.5 4 2Zm4 6h8M8 12h8M8 16h5',
  wallet: 'M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5m14 8h.01',
  file: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M9 13h6M9 17h6',
  map: 'm9 4-6 2v14l6-2 6 2 6-2V4l-6 2-6-2Zm0 0v14m6-12v14',
  chat: 'M21 11.5a8.4 8.4 0 0 1-9 8.4 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 0 1 4 11.5a8.4 8.4 0 0 1 8.5-8.4 8.4 8.4 0 0 1 8.5 8.4Z',
  shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-2-11 1.5 1.5L15 9',
  history: 'M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4 1v4l3 2',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm10 2-4.35-4.35',
  logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9',
  menu: 'M4 6h16M4 12h16M4 18h16',
  close: 'M6 6l12 12M18 6 6 18',
  check: 'm5 13 4 4L19 7',
  alert: 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
  chevron: 'm9 18 6-6-6-6',
  external: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3',
  eye: 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  plus: 'M12 5v14M5 12h14',
  clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-14v5l3 2',
  trend: 'm3 17 6-6 4 4 8-8m0 0h-6m6 0v6',
  mail: 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm-1 1.5 9 6 9-6',
}
</script>

<template>
  <svg
    :width="size" :height="size" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true" focusable="false"
  >
    <path :d="paths[name] ?? paths.gauge" />
  </svg>
</template>
