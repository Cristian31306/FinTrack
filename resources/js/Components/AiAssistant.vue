<script>
import { ref } from 'vue';

const globalIsOpen = ref(false);
const globalMessages = ref([]);
let isChatInitialized = false;
</script>

<script setup>
import { nextTick, watch, onMounted, onUnmounted } from 'vue';
import { MessageSquare, X, Send, Bot, Sparkles, User, Loader2, Paperclip, Mic } from 'lucide-vue-next';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

if (!isChatInitialized) {
    const userName = page.props.auth.user?.name?.split(' ')[0] || '';
    globalMessages.value = [
        {
            role: 'bot',
            content: `Hola ${userName}. Soy tu asistente de inteligencia artificial en FinTrack. ¿En qué te puedo ayudar hoy con tus finanzas?`
        }
    ];
    isChatInitialized = true;
}

const isOpen = globalIsOpen;
const messages = globalMessages;
const isTyping = ref(false);
const message = ref('');
const messagesListRef = ref(null);

// --- Control de Imágenes ---
const imageFile = ref(null);
const imagePreview = ref(null);
const fileInput = ref(null);

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('Por favor selecciona una imagen válida.');
            return;
        }
        imageFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeImage = () => {
    imageFile.value = null;
    imagePreview.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

// --- Control de Voz ---
const isRecording = ref(false);
let recognition = null;

onMounted(() => {
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.lang = 'es-CO';
        recognition.continuous = true;
        recognition.interimResults = true;

        recognition.onresult = (event) => {
            let finalTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript;
                }
            }
            if (finalTranscript) {
                message.value = (message.value + ' ' + finalTranscript).trim();
            }
        };

        recognition.onend = () => {
            if (isRecording.value) {
                isRecording.value = false;
            }
        };

        recognition.onerror = (event) => {
            console.error('Speech recognition error', event.error);
            isRecording.value = false;
        };
    }
});

onUnmounted(() => {
    if (recognition && isRecording.value) {
        recognition.stop();
    }
});

const toggleRecording = () => {
    if (!recognition) {
        alert('Tu navegador no soporta reconocimiento de voz en este momento.');
        return;
    }

    if (isRecording.value) {
        recognition.stop();
        isRecording.value = false;
    } else {
        recognition.start();
        isRecording.value = true;
    }
};

const toggleChat = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        scrollToBottom();
    }
};

const scrollToBottom = async () => {
    await nextTick();
    if (messagesListRef.value) {
        messagesListRef.value.scrollTop = messagesListRef.value.scrollHeight;
    }
};

watch(messages, () => {
    if (isOpen.value) {
        scrollToBottom();
    }
}, { deep: true });

const sendMessage = async () => {
    if (!message.value.trim() && !imagePreview.value || isTyping.value) return;

    if (isRecording.value) {
        toggleRecording();
    }

    const userMsg = message.value.trim() || '📸 [Imagen adjuntada]';
    message.value = '';

    const base64Image = imagePreview.value ? imagePreview.value.split(',')[1] : null;
    const currentMime = imageFile.value?.type;

    // Añadimos el mensaje del usuario
    messages.value.push({ role: 'user', content: userMsg });
    isTyping.value = true;

    try {
        const history = messages.value.slice(1, -1).map(m => ({
            role: m.role,
            content: m.content
        })).slice(-6); // Max history

        const payload = {
            message: userMsg === '📸 [Imagen adjuntada]' ? '' : userMsg,
            history: history
        };

        if (base64Image) {
            payload.image = {
                mime_type: currentMime,
                data: base64Image
            };
        }

        const response = await axios.post(route('ai.chat'), payload);

        // Limpiar imagen si el envío fue exitoso
        removeImage();

        const botReply = response.data.message;
        messages.value.push({ role: 'bot', content: botReply });

        // Si la IA ejecutó una compra, recargamos el estado de la aplicación
        if (botReply.includes('✅')) {
            router.reload();
        }
    } catch (error) {
        messages.value.push({
            role: 'bot',
            content: 'Ups, tuvimos un problema de conexión. ¿Puedes intentar de nuevo?'
        });
        console.error('Error in AI Chat:', error);
    } finally {
        isTyping.value = false;
    }
};

const quickActions = [
    "Registra un gasto a mi tarjeta",
    "¿Cuál es el resumen de mis deudas?",
    "Quiero simular una compra",
    "Dame un consejo financiero"
];

const selectQuickAction = (action) => {
    message.value = action;
    sendMessage();
};
</script>

<template>
    <div class="fixed bottom-6 right-6 z-[100] flex flex-col items-end">
        <!-- Backdrop invisible para detectar el click fuera y cerrar la IA -->
        <div v-if="isOpen" @click="isOpen = false" class="fixed inset-0 z-40 bg-transparent cursor-default"></div>

        <!-- Chat Panel -->
        <transition
            enter-active-class="transition-all duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)] origin-bottom-right"
            enter-from-class="transform translate-y-10 opacity-0 scale-50"
            enter-to-class="transform translate-y-0 opacity-100 scale-100"
            leave-active-class="transition-all duration-300 ease-in origin-bottom-right"
            leave-from-class="transform translate-y-0 opacity-100 scale-100"
            leave-to-class="transform translate-y-10 opacity-0 scale-50">
            <div v-show="isOpen"
                class="mb-4 flex flex-col w-[380px] h-[550px] max-h-[80vh] bg-white rounded-2xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.25)] border border-slate-100 overflow-hidden relative z-50">
                <!-- Header -->
                <div
                    class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-brand-600 to-brand-500 text-white shadow-md z-10">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm shadow-inner">
                            <Sparkles class="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-outfit font-semibold tracking-wide text-lg leading-tight">FinTrack AI</h3>
                            <p class="text-xs text-brand-100">Asistente Financiero Premium</p>
                        </div>
                    </div>
                    <button @click="toggleChat"
                        class="p-2 text-white/80 hover:text-white rounded-full hover:bg-white/20 transition-all focus:outline-none">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Messages -->
                <div ref="messagesListRef" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50">
                    <div v-for="(msg, index) in messages" :key="index" :class="[
                        'flex w-full',
                        msg.role === 'user' ? 'justify-end' : 'justify-start'
                    ]">
                        <div :class="[
                            'flex gap-3 max-w-[85%]',
                            msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'
                        ]">
                            <!-- Avatar -->
                            <div :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full mt-auto',
                                msg.role === 'user' ? 'bg-brand-100 text-brand-600' : 'bg-gradient-to-br from-brand-500 to-indigo-600 text-white shadow-sm'
                            ]">
                                <User v-if="msg.role === 'user'" class="h-4 w-4" />
                                <Bot v-else class="h-4 w-4" />
                            </div>

                            <!-- Bubble -->
                            <div :class="[
                                'p-4 rounded-2xl text-[15px] leading-relaxed shadow-sm',
                                msg.role === 'user'
                                    ? 'bg-brand-600 text-white rounded-br-sm'
                                    : 'bg-white text-slate-700 border border-slate-100 rounded-bl-sm markdown-body'
                            ]">
                                <template v-if="msg.role === 'user'">
                                    {{ msg.content }}
                                </template>
                                <template v-else>
                                    <div v-html="msg.content"></div>

                                    <!-- Botones de Confirmación de Compra (Vista Previa) -->
                                    <div v-if="msg.role === 'bot' && msg.content.includes('Vista previa del registro') && !isTyping"
                                        class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-3">
                                        <button @click="selectQuickAction('Sí, registrar compra')"
                                            class="flex items-center justify-center gap-2 px-4 py-2.5 text-[14px] font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl transition-all shadow-md active:scale-95">
                                            <span>✅ Sí, registrar</span>
                                        </button>
                                        <button @click="selectQuickAction('No, corregir datos')"
                                            class="flex items-center justify-center gap-2 px-4 py-2.5 text-[14px] font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all active:scale-95">
                                            <span>❌ No, corregir</span>
                                        </button>
                                    </div>

                                    <!-- Quick Actions inside bot message (Initial one) -->
                                    <div v-if="index === 0 && messages.length === 1 && !isTyping"
                                        class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-3">
                                        <button v-for="(action, i) in quickActions" :key="i"
                                            @click="selectQuickAction(action)"
                                            class="text-left px-3.5 py-2.5 text-[14px] font-medium text-brand-700 bg-brand-50 border border-brand-100 hover:bg-brand-100 hover:border-brand-200 rounded-xl transition-all hover:scale-[1.01] focus:outline-none shadow-sm">
                                            {{ action }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Typing Indicator -->
                    <div v-if="isTyping" class="flex w-full justify-start">
                        <div class="flex gap-3 max-w-[85%] flex-row">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full mt-auto bg-gradient-to-br from-brand-500 to-indigo-600 text-white shadow-sm">
                                <Bot class="h-4 w-4" />
                            </div>
                            <div
                                class="p-4 rounded-2xl bg-white text-slate-500 border border-slate-100 rounded-bl-sm shadow-sm flex items-center gap-1.5">
                                <span
                                    class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                <span
                                    class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                <span class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce"></span>
                            </div>
                        </div>
                    </div>

                    <div ref="anchor"></div>
                </div>

                <!-- Input box -->
                <div class="p-4 bg-white border-t border-slate-100 flex flex-col gap-3">
                    <div v-if="imagePreview" class="relative mb-2 inline-block self-start">
                        <img :src="imagePreview"
                            class="h-16 w-16 rounded-xl border border-slate-200 object-cover shadow-sm" />
                        <button type="button" @click="removeImage"
                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-sm hover:bg-red-600 transition-colors">
                            <X class="h-3 w-3" />
                        </button>
                    </div>

                    <form @submit.prevent="sendMessage" class="relative flex items-center gap-2">
                        <button type="button" @click="triggerFileInput"
                            class="p-2 text-slate-400 hover:text-brand-600 hover:bg-brand-50 rounded-full transition-colors flex-shrink-0">
                            <Paperclip class="h-5 w-5" />
                        </button>
                        <input type="file" ref="fileInput" @change="handleFileUpload" accept="image/*" class="hidden" />

                        <div class="relative w-full flex items-center">
                            <input v-model="message" type="text"
                                :placeholder="isRecording ? 'Escuchando...' : 'Mensaje a FinTrack AI...'"
                                class="w-full bg-slate-50 border border-slate-200 text-sm text-slate-800 rounded-full pl-4 pr-10 py-3.5 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all placeholder:text-slate-400/80"
                                :class="{ 'ring-2 ring-red-400/50 border-red-400 bg-red-50 text-red-700 placeholder:text-red-400': isRecording }"
                                :disabled="isTyping" />

                            <button type="button" @click="toggleRecording"
                                class="absolute right-2 p-1.5 rounded-full text-slate-400 hover:text-red-500 transition-colors"
                                :class="{ 'text-red-500 animate-pulse bg-red-50': isRecording }">
                                <Mic class="h-5 w-5" />
                            </button>
                        </div>

                        <button type="submit" :disabled="(!message.trim() && !imagePreview) || isTyping"
                            class="p-2.5 rounded-full text-white bg-brand-600 shadow-md shadow-brand-500/30 hover:bg-brand-500 hover:scale-105 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:bg-brand-600 disabled:hover:scale-100 disabled:hover:translate-y-0 transition-all focus:outline-none flex-shrink-0">
                            <Loader2 v-if="isTyping" class="h-5 w-5 animate-spin" />
                            <Send v-else class="h-5 w-5 ml-0.5" />
                        </button>
                    </form>
                    <div class="text-center mt-2 flex items-center justify-center gap-1.5">
                        <Sparkles class="h-3 w-3 text-brand-400" />
                        <span class="text-[10px] text-slate-400 font-medium tracking-wide">Multimodal. Powered by
                            Gemini</span>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Floating Button -->
        <transition enter-active-class="transition-all duration-300 ease-out delay-100"
            enter-from-class="transform scale-0 opacity-0" enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition-all duration-200 ease-in" leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-0 opacity-0">
            <button v-show="!isOpen" @click="toggleChat"
                class="group relative flex h-14 w-14 items-center justify-center rounded-full shadow-lg transition-transform duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-brand-500/30 bg-gradient-to-tr from-brand-600 to-brand-400 text-white">
                <div class="absolute -top-1 -right-1 flex h-4 w-4">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-200 opacity-75"></span>
                    <span class="relative inline-flex h-4 w-4 rounded-full bg-brand-500 border-2 border-white"></span>
                </div>
                <MessageSquare class="h-6 w-6" />
            </button>
        </transition>
    </div>
</template>

<style>
.markdown-body {
    font-size: 15px;
    line-height: 1.6;
}

.markdown-body p {
    margin-bottom: 0.75em;
}

.markdown-body p:last-child {
    margin-bottom: 0;
}

.markdown-body strong {
    font-weight: 600;
    color: #1e293b;
}

.markdown-body ul {
    list-style-type: disc;
    margin-left: 1.5rem;
    margin-bottom: 0.75em;
    margin-top: 0.5em;
}

.markdown-body ol {
    list-style-type: decimal;
    margin-left: 1.5rem;
    margin-bottom: 0.75em;
    margin-top: 0.5em;
}

.markdown-body li {
    margin-bottom: 0.25em;
}

.markdown-body table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1em;
    font-size: 13px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.markdown-body th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    text-align: left;
    padding: 8px 12px;
    border-bottom: 2px solid #e2e8f0;
}

.markdown-body td {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.markdown-body tr:last-child td {
    border-bottom: none;
}

.markdown-body tr:nth-child(even) {
    background-color: #fbfcfe;
}

.markdown-body tr:hover {
    background-color: #f1f5f9;
}
</style>
