<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Simulador WhatsApp - Dev</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">📱 Simulador de Mensagens WhatsApp</h1>
        <p class="text-sm text-gray-600 mb-6">Esta ferramenta simula o recebimento de mensagens do WhatsApp sem precisar usar a Evolution API real.</p>
        
        <form id="simulateForm" class="space-y-4">
            <div>
                <label for="number" class="block text-sm font-medium text-gray-700 mb-2">
                    Número do WhatsApp
                </label>
                <input 
                    type="text" 
                    id="number" 
                    name="number" 
                    value="5511999999999" 
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="5511999999999 ou 5511999999999@s.whatsapp.net"
                >
                <p class="text-xs text-gray-500 mt-1">Use apenas números ou o formato completo com @s.whatsapp.net</p>
            </div>
            
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                    Mensagem
                </label>
                <textarea 
                    id="message" 
                    name="message" 
                    required
                    rows="4"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Digite a mensagem que você quer simular..."
                ></textarea>
            </div>
            
            <div class="flex gap-2">
                <button 
                    type="submit" 
                    class="flex-1 bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                >
                    🚀 Enviar Simulação
                </button>
                <button 
                    type="button" 
                    id="clearBtn"
                    class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 transition"
                >
                    Limpar
                </button>
            </div>
        </form>
        
        <div id="result" class="mt-6 hidden"></div>
        
        <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <h2 class="font-semibold text-yellow-800 mb-2">💡 Dicas de teste:</h2>
            <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                <li>Primeira mensagem: "oi" → deve receber menu</li>
                <li>Keywords: "boleto", "senha", "iptu" → roteamento automático</li>
                <li>Comando menu: "menu" ou "0" → reenvia menu</li>
                <li>Seleção: "1", "2", etc → escolhe setor</li>
            </ul>
        </div>
    </div>

    <script>
        const form = document.getElementById('simulateForm');
        const resultDiv = document.getElementById('result');
        const clearBtn = document.getElementById('clearBtn');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            resultDiv.classList.add('hidden');
            
            const formData = new FormData(form);
            const data = {
                number: formData.get('number'),
                message: formData.get('message'),
            };

            try {
                const response = await fetch('/dev/simulate-whatsapp', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();
                resultDiv.classList.remove('hidden');
                
                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">✅</span>
                                <div>
                                    <h3 class="font-semibold text-green-800">Mensagem simulada com sucesso!</h3>
                                    <p class="text-sm text-green-700 mt-1">
                                        Número: <code class="bg-green-100 px-1 rounded">${data.number}</code><br>
                                        Mensagem: <code class="bg-green-100 px-1 rounded">${data.message}</code>
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                    // Limpa o campo de mensagem após sucesso
                    document.getElementById('message').value = '';
                } else {
                    let errorDetails = result.error || 'Erro desconhecido';
                    if (result.file) {
                        errorDetails += `\n📍 ${result.file}`;
                    }
                    if (result.errors) {
                        errorDetails += `\n⚠️ ${JSON.stringify(result.errors, null, 2)}`;
                    }
                    resultDiv.innerHTML = `
                        <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                            <div class="flex items-start gap-2">
                                <span class="text-2xl">❌</span>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-red-800">Erro ao simular mensagem</h3>
                                    <pre class="text-xs text-red-700 mt-2 whitespace-pre-wrap bg-red-100 p-2 rounded">${errorDetails}</pre>
                                </div>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `
                    <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">❌</span>
                            <div>
                                <h3 class="font-semibold text-red-800">Erro de conexão</h3>
                                <p class="text-sm text-red-700 mt-1">${error.message}</p>
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        clearBtn.addEventListener('click', () => {
            form.reset();
            resultDiv.classList.add('hidden');
            document.getElementById('number').value = '5511999999999';
        });
    </script>
</body>
</html>
