<script setup>
/**
 * محرّر نصّ منسّق (TipTap): شريط أدوات كالذي في Word، وناتجه HTML.
 *
 * الأدوات ما يحتاجه مقال لا أكثر: عنوانان فرعيان، تأكيد، قوائم، اقتباس،
 * رابط، صورة، فاصل. وكل ما يُحفظ ينظّفه الخادم (App\Services\RichText)
 * على الوسوم نفسها، فما لا يقبله الخادم لا يقدّمه المحرّر أصلاً.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import { admin } from '@/api/endpoints'
import { useUiStore } from '@/stores/ui'

const model = defineModel({ type: String, default: '' })
const props = defineProps({
  placeholder: { type: String, default: 'اكتب هنا…' },
  label: { type: String, default: 'نصّ المقال' },
})

const ui = useUiStore()

const editor = useEditor({
  content: model.value,
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      code: false,
      codeBlock: false,
      link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
    }),
    Image.configure({ inline: false }),
    Placeholder.configure({ placeholder: () => props.placeholder }),
  ],
  editorProps: {
    attributes: {
      class: 'rte-content prose-admin focus:outline-none min-h-[24rem]',
      dir: 'rtl',
      'aria-label': props.label,
      role: 'textbox',
      'aria-multiline': 'true',
    },
  },
  onUpdate: ({ editor: e }) => { model.value = output(e) },
})

/**
 * ناتج المحرّر بلا فقراتٍ فارغة في آخره: TipTap يُبقي واحدة بعد الاقتباس
 * أو الصورة ليُكتب بعدهما، والمحرّر الفارغ كلّه <p></p>. لا معنى لهما
 * على الموقع (والخادم يحذفهما أيضاً)، فلا يظهران في المعاينة ولا العدّ.
 */
function output(e) {
  return e.isEmpty ? '' : e.getHTML().replace(/(<p><\/p>)+$/, '')
}

// تحميل مقال بعد إنشاء المحرّر (أو استبداله): يُكتب المحتوى بلا تسجيل تعديل
watch(model, (html) => {
  const e = editor.value
  if (e && html !== output(e)) e.commands.setContent(html || '', { emitUpdate: false })
})

onBeforeUnmount(() => editor.value?.destroy())

/* ── الرابط: شريطٌ صغير لا نافذة متصفح ───────────────────────────────── */

const linking = ref(false)
const linkUrl = ref('')
const linkInput = ref(null)

async function openLink() {
  linkUrl.value = editor.value?.getAttributes('link').href ?? ''
  linking.value = true
  await nextTick()
  linkInput.value?.focus()
}

function applyLink() {
  const url = linkUrl.value.trim()
  const chain = editor.value.chain().focus().extendMarkRange('link')
  if (!url) chain.unsetLink().run()
  else chain.setLink({ href: /^(https?:|mailto:|\/)/i.test(url) ? url : `https://${url}` }).run()
  linking.value = false
}

/* ── الصورة: تُرفع ثم تُدرج ──────────────────────────────────────────── */

const fileInput = ref(null)
const uploading = ref(false)

async function insertImage(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  uploading.value = true
  try {
    const { data } = await admin.posts.uploadImage(file)
    editor.value.chain().focus().setImage({ src: data.data.url, alt: '' }).run()
  } catch (err) {
    ui.error(err.fieldErrors?.image?.[0] ?? err.userMessage ?? 'تعذّر رفع الصورة.')
  } finally {
    uploading.value = false
  }
}

/** أزرار الشريط: [مفتاح، تسمية، أمر، فحص النشاط] */
const groups = [
  [
    { key: 'h2', label: 'عنوان', title: 'عنوان فرعي', run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run(), active: (e) => e.isActive('heading', { level: 2 }) },
    { key: 'h3', label: 'فرعي', title: 'عنوان أصغر', run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run(), active: (e) => e.isActive('heading', { level: 3 }) },
  ],
  [
    { key: 'b', label: 'B', title: 'عريض (Ctrl+B)', cls: 'font-bold', run: (e) => e.chain().focus().toggleBold().run(), active: (e) => e.isActive('bold') },
    { key: 'i', label: 'I', title: 'مائل (Ctrl+I)', cls: 'italic', run: (e) => e.chain().focus().toggleItalic().run(), active: (e) => e.isActive('italic') },
    { key: 'u', label: 'U', title: 'تسطير (Ctrl+U)', cls: 'underline', run: (e) => e.chain().focus().toggleUnderline().run(), active: (e) => e.isActive('underline') },
  ],
  [
    { key: 'ul', label: 'نقاط', title: 'قائمة نقطية', run: (e) => e.chain().focus().toggleBulletList().run(), active: (e) => e.isActive('bulletList') },
    { key: 'ol', label: 'ترقيم', title: 'قائمة مرقّمة', run: (e) => e.chain().focus().toggleOrderedList().run(), active: (e) => e.isActive('orderedList') },
    { key: 'q', label: 'اقتباس', title: 'اقتباس', run: (e) => e.chain().focus().toggleBlockquote().run(), active: (e) => e.isActive('blockquote') },
  ],
]
</script>

<template>
  <div class="rte">
    <!-- الشريط يبقى ظاهراً أثناء الكتابة في نصٍّ طويل -->
    <div
      v-if="editor" role="toolbar" aria-label="أدوات التنسيق"
      class="sticky top-0 z-10 flex flex-wrap items-center gap-1 py-2 mb-4 bg-sand-0/95 backdrop-blur border-b border-sand-100"
    >
      <template v-for="(group, gi) in groups" :key="gi">
        <span v-if="gi" class="w-px h-5 bg-sand-200 mx-0.5" aria-hidden="true" />
        <button
          v-for="b in group" :key="b.key" type="button"
          class="h-8 min-w-8 px-2 rounded-md text-sm transition-colors"
          :class="[b.cls, b.active(editor) ? 'bg-clay-50 text-clay-800' : 'text-sand-700 hover:bg-sand-100']"
          :title="b.title" :aria-label="b.title" :aria-pressed="b.active(editor)"
          @click="b.run(editor)"
        >{{ b.label }}</button>
      </template>

      <span class="w-px h-5 bg-sand-200 mx-0.5" aria-hidden="true" />
      <button
        type="button" class="h-8 px-2 rounded-md text-sm"
        :class="editor.isActive('link') ? 'bg-clay-50 text-clay-800' : 'text-sand-700 hover:bg-sand-100'"
        title="رابط" aria-label="رابط" :aria-pressed="editor.isActive('link')" @click="openLink"
      >رابط</button>
      <button
        type="button" class="h-8 px-2 rounded-md text-sm text-sand-700 hover:bg-sand-100"
        title="صورة داخل النصّ" aria-label="صورة داخل النصّ" :disabled="uploading" @click="fileInput?.click()"
      >{{ uploading ? 'يُرفع…' : 'صورة' }}</button>
      <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" @change="insertImage" />
      <button
        type="button" class="h-8 px-2 rounded-md text-sm text-sand-700 hover:bg-sand-100"
        title="فاصل" aria-label="فاصل" @click="editor.chain().focus().setHorizontalRule().run()"
      >فاصل</button>

      <span class="ms-auto flex items-center gap-1">
        <button type="button" class="h-8 w-8 rounded-md text-sand-600 hover:bg-sand-100 disabled:opacity-40" title="تراجع (Ctrl+Z)" aria-label="تراجع"
                :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">↶</button>
        <button type="button" class="h-8 w-8 rounded-md text-sand-600 hover:bg-sand-100 disabled:opacity-40" title="إعادة (Ctrl+Y)" aria-label="إعادة"
                :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">↷</button>
      </span>

      <!-- شريط الرابط -->
      <form v-if="linking" class="basis-full flex items-center gap-2 pt-2" @submit.prevent="applyLink">
        <label class="sr-only" for="rteLink">عنوان الرابط</label>
        <input id="rteLink" ref="linkInput" v-model="linkUrl" dir="ltr" class="input !py-1.5 flex-1" placeholder="https://…" @keydown.esc.prevent="linking = false" />
        <button class="btn btn-primary !py-1.5 text-sm" type="submit">{{ linkUrl ? 'طبّق' : 'أزل الرابط' }}</button>
        <button class="btn btn-ghost !py-1.5 text-sm" type="button" @click="linking = false">إلغاء</button>
      </form>
    </div>

    <EditorContent :editor="editor" />
  </div>
</template>

<style>
/* النصّ داخل المحرّر بشكله على الموقع: عناوين وقوائم واقتباس */
.prose-admin { font-size: 1.05rem; line-height: 2; color: var(--color-sand-800); }
.prose-admin > * + * { margin-top: 1rem; }
.prose-admin h2 { font-size: 1.5rem; font-weight: 700; line-height: 1.4; color: var(--color-sand-900); margin-top: 2rem; }
.prose-admin h3 { font-size: 1.2rem; font-weight: 700; line-height: 1.45; color: var(--color-sand-900); margin-top: 1.5rem; }
.prose-admin ul { list-style: disc; padding-inline-start: 1.5em; }
.prose-admin ol { list-style: decimal; padding-inline-start: 1.5em; }
.prose-admin li::marker { color: var(--color-clay-600); }
.prose-admin blockquote { border-inline-start: 3px solid var(--color-clay-500); padding-inline-start: 1rem; color: var(--color-sand-600); }
.prose-admin a { color: var(--color-clay-700); text-decoration: underline; text-underline-offset: 0.2em; }
.prose-admin img { max-width: 100%; height: auto; border-radius: 0.75rem; }
.prose-admin img.ProseMirror-selectednode { outline: 3px solid var(--color-clay-400); }
.prose-admin hr { border: 0; border-top: 1px solid var(--color-sand-200); margin: 2rem 0; }
/* النصّ النائب حين يكون المحرّر فارغاً */
.prose-admin p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  color: var(--color-sand-300);
  float: right;
  height: 0;
  pointer-events: none;
}
</style>
