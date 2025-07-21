// Estado global
let urls = [];
let apiToken = null;

// DOMINIO DE LA API - SIEMPRE 0ln.eu
const API_DOMAIN = '0ln.eu';

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', init);

async function init() {
    console.log('Iniciando extensión...');
    await loadApiToken();
    loadUrls();
    setupEventListeners();
}

async function loadApiToken() {
    const result = await chrome.storage.local.get(['apiToken']);
    apiToken = result.apiToken || null;
    if (apiToken) {
        console.log('Token API cargado');
    }
}

function setupEventListeners() {
    document.getElementById('toggleBtn').addEventListener('click', toggleForm);
    document.getElementById('saveBtn').addEventListener('click', addUrl);
    document.getElementById('searchInput').addEventListener('input', filterUrls);
    document.getElementById('importApiBtn').addEventListener('click', importFromAPI);
    document.getElementById('exportBtn').addEventListener('click', exportUrls);
    document.getElementById('clearBtn').addEventListener('click', clearAllUrls);
    document.getElementById('configBtn').addEventListener('click', toggleConfig);
    document.getElementById('saveTokenBtn').addEventListener('click', saveToken);
    document.getElementById('openInTab').addEventListener('click', () => {
        chrome.tabs.create({ url: chrome.runtime.getURL('popup.html') });
    });
    
    document.getElementById('shortUrl').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') addUrl();
    });
}

function toggleConfig() {
    const config = document.getElementById('apiConfig');
    const addForm = document.getElementById('addForm');
    
    // Cerrar otros formularios
    addForm.style.display = 'none';
    document.getElementById('toggleBtn').textContent = '➕ Agregar URL';
    
    if (config.style.display === 'none' || config.style.display === '') {
        config.style.display = 'block';
        // Cargar token actual si existe
        if (apiToken) {
            document.getElementById('apiToken').value = apiToken;
        }
    } else {
        config.style.display = 'none';
    }
}

async function saveToken() {
    const tokenInput = document.getElementById('apiToken');
    const token = tokenInput.value.trim();
    
    if (token) {
        apiToken = token;
        await chrome.storage.local.set({ apiToken: token });
        showToast('✅ Token guardado');
    } else {
        apiToken = null;
        await chrome.storage.local.remove('apiToken');
        showToast('🗑️ Token eliminado');
    }
    
    toggleConfig();
    updateStats(); // Actualizar para mostrar el indicador de token
}

function loadUrls() {
    chrome.storage.local.get(['urls'], function(result) {
        urls = result.urls || [];
        console.log('URLs cargadas:', urls.length);
        renderUrls();
        updateStats();
    });
}

function updateStats() {
    const totalUrls = urls.length;
    const domains = [...new Set(urls.map(u => {
        try {
            return new URL(u.shortUrl).hostname;
        } catch {
            return 'unknown';
        }
    }))];
    
    let statsText = `📊 ${totalUrls} URLs | 🌐 ${domains.length} dominios`;
    
    if (apiToken) {
        statsText += ' | 🔑 Con token';
    }
    
    document.getElementById('stats').textContent = statsText;
}

function renderUrls(urlsToRender = urls) {
    const list = document.getElementById('urlList');
    
    if (urlsToRender.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h4>No hay URLs guardadas</h4>
                <p>Agrega tu primera URL corta</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = urlsToRender.map((url, index) => {
        const realIndex = urls.indexOf(url);
        const domain = extractDomain(url.shortUrl);
        
        return `
            <div class="url-item" data-index="${realIndex}">
                <div class="url-actions">
                    <button class="btn-action btn-copy" data-action="copy" data-index="${realIndex}" title="Copiar">📋</button>
                    <button class="btn-action btn-delete" data-action="delete" data-index="${realIndex}" title="Eliminar">🗑️</button>
                </div>
                
                <div class="url-header">
                    <img src="https://www.google.com/s2/favicons?domain=${domain}" class="favicon" onerror="this.style.display='none'">
                    <div class="url-title">${escapeHtml(url.title || domain)}</div>
                </div>
                
                <div class="url-short">🔗 ${escapeHtml(url.shortUrl)}</div>
                ${url.originalUrl ? `<div class="url-original" style="font-size: 12px; color: #666; margin-top: 4px;">➡️ ${escapeHtml(url.originalUrl)}</div>` : ''}
            </div>
        `;
    }).join('');
    
    setupUrlEventListeners();
}

function setupUrlEventListeners() {
    document.querySelectorAll('[data-action="copy"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const index = parseInt(this.getAttribute('data-index'));
            copyUrl(index);
        });
    });
    
    document.querySelectorAll('[data-action="delete"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const index = parseInt(this.getAttribute('data-index'));
            deleteUrl(index, this);
        });
    });
    
    document.querySelectorAll('.url-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-action')) {
                const index = parseInt(this.getAttribute('data-index'));
                if (urls[index]) {
                    chrome.tabs.create({ url: urls[index].shortUrl });
                }
            }
        });
    });
}

function copyUrl(index) {
    const url = urls[index];
    if (!url) return;
    
    navigator.clipboard.writeText(url.shortUrl).then(() => {
        showToast('✅ URL copiada');
    }).catch(() => {
        const input = document.createElement('input');
        input.value = url.shortUrl;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        showToast('✅ URL copiada');
    });
}

// DELETE CORREGIDO - Siempre usa 0ln.eu para la API
async function deleteUrl(index, buttonElement) {
    const url = urls[index];
    if (!url) {
        console.error('No hay URL en índice:', index);
        return;
    }
    
    const urlItem = buttonElement.closest('.url-item');
    
    if (buttonElement.classList.contains('confirm-delete')) {
        try {
            buttonElement.innerHTML = '⏳';
            buttonElement.disabled = true;
            
            // Extraer información
            const urlObj = new URL(url.shortUrl);
            const shortCode = urlObj.pathname.substring(1);
            const urlDomain = urlObj.hostname; // Dominio de la URL (puede ser Clancy.es, etc)
            
            console.log('Eliminando:', { 
                shortCode, 
                urlDomain, 
                apiDomain: API_DOMAIN,
                hasToken: !!apiToken 
            });
            
            // Intentar eliminar del servidor
            let serverDeleted = false;
            let deleteMethod = 'none';
            
            // Método 1: Intentar con token si existe
            if (apiToken) {
                try {
                    // IMPORTANTE: Siempre usar API_DOMAIN (0ln.eu) para la API
                    const apiUrl = `https://${API_DOMAIN}/api/delete-url.php`;
                    console.log('Llamando a:', apiUrl);
                    
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${apiToken}`,
                            'X-API-Token': apiToken
                        },
                        body: JSON.stringify({
                            code: shortCode
                        })
                    });
                    
                    console.log('Respuesta:', response.status);
                    
                    if (response.ok) {
                        const result = await response.json();
                        console.log('Resultado:', result);
                        
                        if (result.success) {
                            serverDeleted = true;
                            deleteMethod = 'token';
                            console.log('Eliminado del servidor exitosamente');
                        } else {
                            console.log('Error del servidor:', result.message);
                        }
                    } else {
                        const errorText = await response.text();
                        console.log('Error HTTP:', response.status, errorText);
                    }
                } catch (error) {
                    console.error('Error con token:', error);
                }
            }
            
            // Método 2: Intentar con sesión (por si acaso)
            if (!serverDeleted) {
                try {
                    const response = await fetch(`https://${API_DOMAIN}/api/delete-url.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        mode: 'cors',
                        body: JSON.stringify({
                            code: shortCode
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            serverDeleted = true;
                            deleteMethod = 'session';
                            console.log('Eliminado con sesión');
                        }
                    }
                } catch (error) {
                    console.log('Error con sesión:', error);
                }
            }
            
            // Método 3: Si no funciona, abrir en navegador para eliminar
            if (!serverDeleted && !apiToken) {
                const deleteUrl = `https://${API_DOMAIN}/dashboard?action=delete&code=${shortCode}`;
                const confirmDelete = confirm(
                    'No se puede eliminar del servidor desde la extensión.\n\n' +
                    '¿Quieres abrir el panel para eliminarla?\n\n' +
                    'Nota: Puedes configurar un token API para eliminar directamente.'
                );
                
                if (confirmDelete) {
                    chrome.tabs.create({ url: deleteUrl });
                }
            }
            
            // Siempre eliminar localmente
            urlItem.classList.add('deleting');
            await new Promise(resolve => setTimeout(resolve, 300));
            
            urls.splice(index, 1);
            await chrome.storage.local.set({ urls: urls });
            
            renderUrls();
            updateStats();
            
            // Mostrar mensaje apropiado
            if (serverDeleted) {
                showToast(`✅ Eliminada completamente (${deleteMethod})`);
            } else if (apiToken) {
                showToast('🗑️ Eliminada localmente\n(Error en servidor)');
            } else {
                showToast('🗑️ Eliminada localmente\n(Configura token para eliminar del servidor)');
            }
            
        } catch (error) {
            console.error('Error al eliminar:', error);
            buttonElement.innerHTML = '🗑️';
            buttonElement.disabled = false;
            buttonElement.classList.remove('confirm-delete');
            urlItem.classList.remove('deleting');
            showToast('❌ Error al eliminar');
        }
    } else {
        buttonElement.classList.add('confirm-delete');
        buttonElement.innerHTML = '✓?';
        buttonElement.title = 'Click para confirmar';
        
        if (!apiToken) {
            showToast('💡 Configura un token API para eliminar del servidor');
        }
        
        setTimeout(() => {
            if (buttonElement && !buttonElement.disabled) {
                buttonElement.classList.remove('confirm-delete');
                buttonElement.innerHTML = '🗑️';
                buttonElement.title = 'Eliminar';
            }
        }, 3000);
    }
}

// IMPORT también corregido para usar siempre 0ln.eu
async function importFromAPI() {
    const btn = document.getElementById('importApiBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Importando...';
    
    try {
        // Siempre importar desde el dominio principal
        const apiUrl = `https://${API_DOMAIN}/api/my-urls.php`;
        console.log('Importando desde:', apiUrl);
        
        // Preparar headers
        const headers = {
            'Content-Type': 'application/json',
        };
        
        if (apiToken) {
            headers['Authorization'] = `Bearer ${apiToken}`;
            headers['X-API-Token'] = apiToken;
        }
        
        const response = await fetch(apiUrl, {
            credentials: 'include',
            mode: 'cors',
            headers: headers
        });
        
        if (!response.ok) {
            throw new Error('No autorizado');
        }
        
        const apiUrls = await response.json();
        let imported = 0;
        
        apiUrls.forEach(apiUrl => {
            const shortUrl = apiUrl.short_url;
            if (!urls.find(u => u.shortUrl === shortUrl)) {
                urls.unshift({
                    shortUrl: shortUrl,
                    title: apiUrl.short_code || 'Importado',
                    originalUrl: apiUrl.original_url || null,
                    date: apiUrl.created_at || new Date().toISOString()
                });
                imported++;
            }
        });
        
        if (imported > 0) {
            await chrome.storage.local.set({ urls: urls });
            renderUrls();
            updateStats();
            showToast(`✅ ${imported} URLs importadas de ${API_DOMAIN}`);
        } else {
            showToast('ℹ️ No hay URLs nuevas');
        }
        
    } catch (error) {
        console.error('Error:', error);
        if (apiToken) {
            alert('Error al importar. Verifica tu token API.');
        } else {
            alert(`Error al importar. Asegúrate de estar logueado en ${API_DOMAIN} o configura un token API.`);
        }
    } finally {
        btn.disabled = false;
        btn.textContent = '📥 Importar';
    }
}

// FUNCIÓN ADDURL ACTUALIZADA - CREA URLS CORTAS
async function addUrl() {
    const originalUrlInput = document.getElementById('shortUrl'); // Este campo ahora recibe URLs largas
    const titleInput = document.getElementById('title');
    const originalUrl = originalUrlInput.value.trim();
    const title = titleInput.value.trim();
    
    if (!originalUrl) {
        showError('Por favor ingresa una URL');
        return;
    }
    
    if (!isValidUrl(originalUrl)) {
        showError('Por favor ingresa una URL válida');
        return;
    }
    
    // Verificar si NO es token o sesión
    if (!apiToken) {
        showError('Necesitas configurar un token API para crear URLs');
        showToast('⚠️ Configura primero tu token API');
        return;
    }
    
    // Mostrar loading
    showLoading(true);
    hideError();
    
    try {
        // Crear URL corta en el servidor
        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${apiToken}`,
            'X-API-Token': apiToken
        };
        
        const response = await fetch(`https://${API_DOMAIN}/api/shorten.php`, {
            method: 'POST',
            headers: headers,
            credentials: 'include',
            body: JSON.stringify({
                url: originalUrl,
                original_url: originalUrl // Algunos endpoints esperan este nombre
            })
        });
        
        console.log('Respuesta shorten:', response.status);
        
        if (!response.ok) {
            const errorData = await response.text();
            console.error('Error response:', errorData);
            
            try {
                const error = JSON.parse(errorData);
                throw new Error(error.message || error.error || 'Error al crear URL');
            } catch {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
        }
        
        const result = await response.json();
        console.log('URL creada:', result);
        
        // Guardar la nueva URL
        const newUrl = {
            shortUrl: result.short_url,
            title: title || result.short_code || extractTitle(originalUrl),
            originalUrl: originalUrl,
            date: result.created_at || new Date().toISOString()
        };
        
        // Verificar que no exista ya
        if (!urls.find(u => u.shortUrl === newUrl.shortUrl)) {
            urls.unshift(newUrl);
            await chrome.storage.local.set({ urls: urls });
        }
        
        // Limpiar y actualizar
        originalUrlInput.value = '';
        titleInput.value = '';
        toggleForm();
        renderUrls();
        updateStats();
        
        // Copiar al portapapeles automáticamente
        try {
            await navigator.clipboard.writeText(result.short_url);
            showToast('✅ URL creada y copiada:\n' + result.short_url);
        } catch {
            showToast('✅ URL creada:\n' + result.short_url);
        }
        
    } catch (error) {
        console.error('Error al crear URL:', error);
        showError(error.message || 'Error al crear la URL');
        showToast('❌ ' + (error.message || 'Error al crear URL'));
    } finally {
        showLoading(false);
    }
}

// Funciones auxiliares para el formulario
function showLoading(show) {
    const loadingMsg = document.getElementById('loadingMsg');
    const saveBtn = document.getElementById('saveBtn');
    
    if (loadingMsg) {
        loadingMsg.style.display = show ? 'block' : 'none';
    }
    if (saveBtn) {
        saveBtn.disabled = show;
        saveBtn.textContent = show ? '⏳ Creando...' : '💾 Guardar';
    }
}

function showError(message) {
    const errorMsg = document.getElementById('errorMsg');
    if (errorMsg) {
        errorMsg.textContent = message;
        errorMsg.style.display = 'block';
    }
}

function hideError() {
    const errorMsg = document.getElementById('errorMsg');
    if (errorMsg) {
        errorMsg.style.display = 'none';
    }
}

function extractTitle(url) {
    try {
        const urlObj = new URL(url);
        let title = urlObj.hostname.replace('www.', '');
        // Si hay path, añadir parte de él
        if (urlObj.pathname && urlObj.pathname !== '/') {
            const pathPart = urlObj.pathname.split('/').filter(p => p).join(' - ');
            if (pathPart.length < 50) {
                title += ' - ' + pathPart;
            }
        }
        return title;
    } catch {
        return 'Mi enlace';
    }
}

async function clearAllUrls() {
    if (urls.length === 0) {
        showToast('No hay URLs para eliminar');
        return;
    }
    
    const btn = document.getElementById('clearBtn');
    const originalText = btn.textContent;
    
    if (!btn.classList.contains('confirm-clear')) {
        btn.classList.add('confirm-clear');
        btn.style.background = '#c0392b';
        btn.textContent = `⚠️ ¿Eliminar ${urls.length} URLs?`;
        
        setTimeout(() => {
            btn.classList.remove('confirm-clear');
            btn.style.background = '#e74c3c';
            btn.textContent = originalText;
        }, 5000);
        
        return;
    }
    
    try {
        btn.disabled = true;
        btn.textContent = '⏳ Eliminando...';
        
        const items = document.querySelectorAll('.url-item');
        items.forEach((item, index) => {
            setTimeout(() => {
                item.classList.add('deleting');
            }, index * 50);
        });
        
        await new Promise(resolve => setTimeout(resolve, items.length * 50 + 300));
        
        urls = [];
        await chrome.storage.local.set({ urls: urls });
        
        renderUrls();
        updateStats();
        showToast('🗑️ Todas las URLs eliminadas localmente');
        
    } catch (error) {
        console.error('Error:', error);
        showToast('❌ Error al eliminar');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
        btn.classList.remove('confirm-clear');
        btn.style.background = '#e74c3c';
    }
}

function toggleForm() {
    const form = document.getElementById('addForm');
    const btn = document.getElementById('toggleBtn');
    const config = document.getElementById('apiConfig');
    
    // Cerrar config si está abierto
    config.style.display = 'none';
    
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        btn.textContent = '✖️ Cancelar';
        document.getElementById('shortUrl').focus();
    } else {
        form.style.display = 'none';
        btn.textContent = '➕ Agregar URL';
        hideError();
    }
}

function exportUrls() {
    if (urls.length === 0) {
        alert('No hay URLs para exportar');
        return;
    }
    
    const data = {
        exported_at: new Date().toISOString(),
        total: urls.length,
        urls: urls
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `urls_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    
    showToast(`✅ ${urls.length} URLs exportadas`);
}

function filterUrls(e) {
    const term = e.target.value.toLowerCase();
    
    if (!term) {
        renderUrls();
        return;
    }
    
    const filtered = urls.filter(url =>
        url.title.toLowerCase().includes(term) ||
        url.shortUrl.toLowerCase().includes(term) ||
        (url.originalUrl && url.originalUrl.toLowerCase().includes(term))
    );
    
    renderUrls(filtered);
}

// Funciones auxiliares
function extractDomain(url) {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function isValidUrl(string) {
    try {
        const url = new URL(string);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

function showToast(message) {
    const existing = document.querySelector('.copy-toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'copy-toast';
    toast.textContent = message;
    toast.style.whiteSpace = 'pre-line';
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

console.log('Gestor URLs v1.0.2 - API Domain:', API_DOMAIN);