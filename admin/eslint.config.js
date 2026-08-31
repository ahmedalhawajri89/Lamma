import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import globals from 'globals'

/**
 * فحص ساكن للوحة.
 *
 * `package.json` كان فيه سكربت `lint` بلا ملف إعدادات — أمرٌ يسقط بخطأ عند
 * أول تشغيل، فلم يكن يُشغَّل. وفحصٌ لا يُشغَّل ليس فحصاً.
 *
 * والمجموعة مقصودة الضيق: قواعد تلتقط عيوباً حقيقية لا اختلافات ذوق.
 * التنسيق شأن المحرِّر، وقاعدةٌ عن الفواصل تُغرق النتيجة بضجيجٍ يُعلّم
 * الفريق تجاهلها.
 */
export default [
  { ignores: ['dist/**', 'node_modules/**', '../api/public/admin-app/**'] },

  js.configs.recommended,
  ...vue.configs['flat/recommended'],

  {
    files: ['**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 2024,
      sourceType: 'module',
      globals: { ...globals.browser },
    },
    rules: {
      /**
       * الأسماء المستوردة بلا استعمال تُمنع، والمعاملات لا.
       *
       * استيرادٌ منسيّ يعني حزمةً أكبر ووهماً بأن الملف يعتمد على ما لا
       * يعتمد عليه. أما معاملٌ غير مستعمل فقد يكون واجبَ توقيعٍ (‏`(_, index)`)،
       * ومنعه يدفع إلى حيَل تُشوّه التوقيع.
       */
      'no-unused-vars': ['error', {
        args: 'none',
        caughtErrors: 'none',
        varsIgnorePattern: '^_',
      }],

      'no-console': ['warn', { allow: ['warn', 'error'] }],
      eqeqeq: ['error', 'smart'],
      'prefer-const': 'error',
      'no-var': 'error',

      // `v-html` يحقن HTML خاماً — ولا نعرض هنا إلا ما يأتي من الـ API
      'vue/no-v-html': 'error',

      'vue/multi-word-component-names': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/html-self-closing': 'off',
      'vue/multiline-html-element-content-newline': 'off',
      'vue/html-indent': 'off',
      'vue/html-closing-bracket-newline': 'off',
      'vue/attributes-order': 'off',
      'vue/first-attribute-linebreak': 'off',
    },
  },

  {
    files: ['tests/**/*.js', '*.config.js'],
    languageOptions: { globals: { ...globals.node } },
    rules: { 'no-console': 'off' },
  },
]
