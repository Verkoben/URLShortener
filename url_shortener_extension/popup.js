// Variables globales
let urls = [];
let draggedElement = null;

// Configuración de API
const API_CONFIG = {
    defaultDomain: '0ln.eu',
    getApiDomain: async function() {
        const result = await chrome.storage.local.get(['apiDomain']);
        return result.apiDomain || this.defaultDomain;
    }
};

// Esperar a que el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    loadUrls();
    
    // Event listeners principales
    document.getElementById('toggleBtn').addEventListener('click', toggleForm);
    document.getElementById('saveBtn').addEventListener('click', addUrl);
    document.getElementById('searchInput').addEventListener('input', filterUrls);
    
    // Botones de importación/exportación
    document.getElementById('importApiBtn').addEventListener('click', importFromAPI);
    document.getElementById('importFileBtn').addEventListener('click', () => {
        document.getElementById('fileInput').click();
    });
    document.getElementById('fileInput').addEventListener('change', handleFileImport);
    document.getElementById('exportBtn').addEventListener('click', exportUrls);
    document.getElementById('clearBtn').addEventListener('click', clearAllUrls);
    
    // Botones del header - MODIFICADO para no usar tabs
    document.getElementById('openInTab').addEventListener('click', function() {
        // Usar window.open en lugar de chrome.tabs
        window.open(chrome.runtime.getURL('popup.html'), '_blank');
    });
    
    document.getElementById('openInWindow').addEventListener('click', function() {
        // Abrir en ventana popup
        window.open(
            chrome.runtime.getURL('popup.html'),
            'URLManager',
            'width=450,height=600,left=100,top=100'
        );
    });
    
    // Enter para guardar
    document.getElementById('shortUrl').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') addUrl();
    });
    document.getElementById('title').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') addUrl();
    });
});

function loadUrls() {
    chrome.storage.local.get(['urls'], function(result) {
        urls = result.urls || [];
        renderUrls();
        updateStats();
    });
}

function updateStats() {
    const totalUrls = urls.length;
    const domains = [...new Set(urls.map(u => extractDomain(u.shortUrl)))];
    document.getElementById('stats').textContent = 
        `📊 ${totalUrls} URLs guardadas | 🌐 ${domains.length} dominios`;
}

function renderUrls(urlsToRender = urls) {
    const list = document.getElementById('urlList');
    
    if (urlsToRender.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h4>No hay URLs guardadas</h4>
                <p>Agrega tu primera URL corta o importa desde tu servidor</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = urlsToRender.map((url, index) => {
        const realIndex = urls.indexOf(url);
        const favicon = url.favicon || `https://www.google.com/s2/favicons?domain=${extractDomain(url.originalUrl || url.shortUrl)}`;
        const domain = extractDomain(url.shortUrl);
        
        return `
            <div class="url-item" data-index="${realIndex}" draggable="true">
                <div class="url-actions">
                    <button class="btn-action btn-copy" data-index="${realIndex}" title="Copiar URL corta">📋</button>
                    <button class="btn-action btn-delete" data-index="${realIndex}" title="Eliminar">🗑️</button>
                </div>
                
                <div class="url-header">
                    <img src="${favicon}" class="favicon" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAbwAAAG8B8aLcQwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAGJSURBVDiNpZO/S1VRGMc/5773vuddhKRID7oYRBAUQdHQ0tLQ0H9QQ0tDRGNTEERDQ0NIS1BQUUN/QUOJg4iBUBAU1NCPwaLXe+/7Ps5734aEet97r/2GL5zD93w/53vO+cIJlFJpYBKYBkaALiABBMCu1noHWANWgVUp5W7zWxEAKaVSylFgtlKpXAiCwPE8L66UQmuN1hoA13VDy7J2HMf5bFnWGyHEm9jnMjBZqVTOFQqF1EaxyN7eHkEQ1HeNokT6+/sZHBykUCjcKZfLs4ODgwtRgJlSqXRqu1hku1gk1dJC+K843H9WqZTi186O0pvNniqVShO9vb3PIwDXPM9LbW1v09baSmJmpglOo7jdbMb1PAdwIwKg4Hle3Pf9lvO53Ik4AIh1dKDrBVgFRGgcOl4dP8+tXqDZCQAJtNZYlkWru4/jODiWhWVZiEaNyDhqJKLN+s7/nKfOnwJUa7VaBtBAZiQ5SfW4OFquzqwWBsFRgBCi1tfXt5vP59O1Wu50MyCXy30XQhxE6v4AWq1/NCuOwOkAAAAASUVORK5CYII='">
                    <div class="url-title">${escapeHtml(url.title)}</div>
                </div>
                
                <div class="url-short">
                    🔗 ${escapeHtml(url.shortUrl)}
                    <span class="domain-tag">${escapeHtml(domain)}</span>
                </div>
                
                ${url.originalUrl ? `
                    <div class="url-original" title="${escapeHtml(url.originalUrl)}">
                        ➡️ ${escapeHtml(url.originalUrl)}
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
    
    setupUrlEventListeners();
    setupDragAndDrop();
}

async function importFromAPI() {
    const btn = document.getElementById('importApiBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Importando...';
    
    try {
        const apiDomain = await API_CONFIG.getApiDomain();
        const customDomain = prompt(`¿Desde qué dominio quieres importar?\n(Deja vacío para ${apiDomain})`, apiDomain);
        const domain = customDomain && customDomain.trim() ? 
            customDomain.trim().replace(/^https?:\/\//, '').replace(/\/$/, '') : 
            apiDomain;
        
        const response = await fetch(`https://${domain}/api/my-urls.php`, {
            credentials: 'include',
            mode: 'cors'
        });
        
        if (!response.ok) {
            throw new Error('No se pudo conectar al servidor');
        }
        
        const apiUrls = await response.json();
        
        if (!Array.isArray(apiUrls)) {
            throw new Error('Formato de respuesta inválido');
        }
        
        let imported = 0;
        const newUrls = [];
        
        for (const apiUrl of apiUrls) {
            const shortUrl = apiUrl.short_url || `https://${apiUrl.domain || domain}/${apiUrl.short_code}`;
            
            if (!urls.find(u => u.shortUrl === shortUrl)) {
                newUrls.push({
                    shortUrl: shortUrl,
                    title: apiUrl.title || apiUrl.short_code || 'Sin título',
                    originalUrl: apiUrl.original_url || null,
                    favicon: null,
                    date: apiUrl.created_at || new Date().toISOString(),
                    clicks: apiUrl.clicks || 0
                });
                imported++;
            }
        }
        
        if (imported > 0) {
            urls = [...newUrls, ...urls];
            await chrome.storage.local.set({ urls: urls });
            renderUrls();
            updateStats();
            showToast(`✅ ${imported} URLs importadas de ${domain}`);
        } else {
            showToast('ℹ️ No hay URLs nuevas para importar');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showToast('❌ Error al importar. ¿Estás logueado en el sitio?');
    } finally {
        btn.disabled = false;
        btn.textContent = '📥 Importar del servidor';
    }
}

function handleFileImport(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const importedData = JSON.parse(e.target.result);
            let urlsToImport = [];
            
            if (Array.isArray(importedData)) {
                urlsToImport = importedData;
            } else if (importedData.urls && Array.isArray(importedData.urls)) {
                urlsToImport = importedData.urls;
            } else {
                throw new Error('Formato de archivo no reconocido');
            }
            
            let imported = 0;
            
            urlsToImport.forEach(url => {
                if (url.shortUrl || (url.short_code && url.domain)) {
                    const shortUrl = url.shortUrl || `https://${url.domain}/${url.short_code}`;
                    
                    if (!urls.find(u => u.shortUrl === shortUrl)) {
                        urls.unshift({
                            shortUrl: shortUrl,
                            title: url.title || url.short_code || 'Importado',
                            originalUrl: url.originalUrl || url.original_url || null,
                            favicon: url.favicon || null,
                            date: url.date || url.created_at || new Date().toISOString()
                        });
                        imported++;
                    }
                }
            });
            
            if (imported > 0) {
                await chrome.storage.local.set({ urls: urls });
                renderUrls();
                updateStats();
                showToast(`✅ ${imported} URLs importadas del archivo`);
            } else {
                showToast('ℹ️ No hay URLs nuevas en el archivo');
            }
            
        } catch (error) {
            console.error('Error:', error);
            showToast('❌ Error al leer el archivo');
        }
    };
    
    reader.readAsText(file);
    e.target.value = '';
}

function exportUrls() {
    if (urls.length === 0) {
        showToast('No hay URLs para exportar');
        return;
    }
    
    const exportData = {
        exported_at: new Date().toISOString(),
        total: urls.length,
        urls: urls
    };
    
    const dataStr = JSON.stringify(exportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(dataBlob);
    link.download = `urls_backup_${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    
    showToast(`✅ ${urls.length} URLs exportadas`);
}

function clearAllUrls() {
    if (urls.length === 0) {
        showToast('No hay URLs para eliminar');
        return;
    }
    
    if (confirm(`¿Eliminar todas las ${urls.length} URLs?\n\n⚠️ Esta acción no se puede deshacer`)) {
        urls = [];
        chrome.storage.local.set({ urls: urls }, function() {
            renderUrls();
            updateStats();
            showToast('🗑️ Todas las URLs eliminadas');
        });
    }
}

function setupUrlEventListeners() {
    // MODIFICADO - usar window.open en lugar de chrome.tabs
    document.querySelectorAll('.url-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.btn-action')) return;
            
            const index = this.getAttribute('data-index');
            if (urls[index]) {
                // Usar window.open en lugar de chrome.tabs.create
                window.open(urls[index].shortUrl, '_blank');
            }
        });
    });
    
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const index = parseInt(this.getAttribute('data-index'));
            copyUrl(index);
        });
    });
    
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const index = parseInt(this.getAttribute('data-index'));
            deleteUrl(index);
        });
    });
}

function setupDragAndDrop() {
    const items = document.querySelectorAll('.url-item');
    
    items.forEach((item) => {
        item.addEventListener('dragstart', function(e) {
            draggedElement = parseInt(this.getAttribute('data-index'));
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        
        item.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
        });
        
        item.addEventListener('dragover', function(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        });
        
        item.addEventListener('drop', function(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            const dropIndex = parseInt(this.getAttribute('data-index'));
            
            if (draggedElement !== null && draggedElement !== dropIndex) {
                const draggedItem = urls[draggedElement];
                urls.splice(draggedElement, 1);
                urls.splice(dropIndex, 0, draggedItem);
                
                chrome.storage.local.set({ urls: urls }, function() {
                    renderUrls();
                    showToast('📋 URLs reordenadas');
                });
            }
            
            return false;
        });
    });
}

function toggleForm() {
    const form = document.getElementById('addForm');
    const btn = document.getElementById('toggleBtn');
    
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

// Función para agregar URL - ahora también acorta URLs largas
async function addUrl() {
    const shortUrlInput = document.getElementById('shortUrl');
    const titleInput = document.getElementById('title');
    let urlToSave = shortUrlInput.value.trim();
    let title = titleInput.value.trim();
    
    if (!urlToSave) {
        showError('Por favor ingresa una URL');
        return;
    }
    
    // Verificar si es una URL válida
    if (!isValidUrl(urlToSave)) {
        showError('Por favor ingresa una URL válida');
        return;
    }
    
    showLoading(true);
    hideError();
    
    try {
        // Detectar si es una URL larga que necesita ser acortada
        const urlObj = new URL(urlToSave);
        const isShortUrl = urlObj.pathname.length > 1 && 
                          urlObj.pathname.length < 20 && 
                          !urlObj.pathname.includes('/') && 
                          (urlObj.hostname.includes('0ln.') || urlObj.hostname.includes('bit.ly') || urlObj.hostname.includes('tinyurl.com'));
        
        // Si NO es una URL corta, acortarla
        if (!isShortUrl) {
            const apiDomain = await API_CONFIG.getApiDomain();
            
            // Hacer petición para acortar
            const response = await fetch(`https://${apiDomain}/api/shorten.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    url: urlToSave,
                    original_url: urlToSave
                })
            });
            
            if (!response.ok) {
                // Si no está autenticado, mostrar mensaje específico
                if (response.status === 401) {
                    throw new Error(`No estás autenticado en ${apiDomain}. Por favor, inicia sesión primero.`);
                }
                throw new Error('No se pudo acortar la URL');
            }
            
            const result = await response.json();
            
            if (result.success && result.short_url) {
                // Usar la URL acortada
                urlToSave = result.short_url;
                showToast('🔗 URL acortada exitosamente');
            } else {
                throw new Error(result.error || 'Error al acortar URL');
            }
        }
        
        // Verificar si ya existe
        const exists = urls.some(u => u.shortUrl === urlToSave);
        if (exists) {
            showError('Esta URL ya está guardada');
            showLoading(false);
            return;
        }
        
        // Si no hay título, generarlo
        if (!title) {
            if (!isShortUrl) {
                // Para URLs largas, usar el dominio
                title = extractDomain(shortUrlInput.value) || 'Nueva URL';
            } else {
                // Para URLs cortas, usar el código
                title = extractShortCode(urlToSave) || 'URL Corta';
            }
        }
        
        // Crear objeto URL
        const newUrl = {
            shortUrl: urlToSave,
            title: title,
            originalUrl: isShortUrl ? null : shortUrlInput.value,
            favicon: `https://www.google.com/s2/favicons?domain=${extractDomain(shortUrlInput.value)}`,
            date: new Date().toISOString(),
            clicks: 0
        };
        
        // Agregar al principio
        urls.unshift(newUrl);
        
        // Guardar
        await chrome.storage.local.set({ urls: urls });
        
        // Limpiar formulario
        shortUrlInput.value = '';
        titleInput.value = '';
        toggleForm();
        renderUrls();
        updateStats();
        showLoading(false);
        showToast('✅ URL guardada');
        
        // Si es una URL corta existente, intentar obtener info adicional
        if (isShortUrl) {
            fetchAdditionalInfo(newUrl, 0);
        }
        
    } catch (error) {
        console.error('Error:', error);
        showError(error.message || 'Error al procesar la URL');
        showLoading(false);
    }
}

// Función para obtener información adicional sin bloquear
async function fetchAdditionalInfo(urlObj, index) {
    try {
        const url = new URL(urlObj.shortUrl);
        const domain = url.hostname;
        const code = url.pathname.substring(1);
        
        if (!code) return;
        
        // Intentar con el endpoint info.php
        const response = await fetch(`https://${domain}/api/info.php?code=${code}`, {
            mode: 'cors',
            credentials: 'omit'
        });
        
        if (response.ok) {
            const data = await response.json();
            
            // Actualizar la URL con la info obtenida
            const currentUrls = await chrome.storage.local.get(['urls']);
            if (currentUrls.urls) {
                const urlIndex = currentUrls.urls.findIndex(u => u.shortUrl === urlObj.shortUrl);
                if (urlIndex !== -1) {
                    // Solo actualizar si no tiene título personalizado
                    if (currentUrls.urls[urlIndex].title === urlObj.title && data.title) {
                        currentUrls.urls[urlIndex].title = data.title;
                    }
                    if (data.original_url) {
                        currentUrls.urls[urlIndex].originalUrl = data.original_url;
                    }
                    if (data.clicks !== undefined) {
                        currentUrls.urls[urlIndex].clicks = data.clicks;
                    }
                    
                    await chrome.storage.local.set({ urls: currentUrls.urls });
                    // Re-renderizar solo si es visible
                    if (document.getElementById('urlList')) {
                        renderUrls();
                    }
                }
            }
        }
    } catch (error) {
        // Ignorar errores, la info adicional es opcional
        console.log('Info adicional no disponible para:', urlObj.shortUrl);
    }
}

function filterUrls(e) {
    const searchTerm = e.target.value.toLowerCase();
    
    if (!searchTerm) {
        renderUrls();
        return;
    }
    
    const filtered = urls.filter(url => 
        url.title.toLowerCase().includes(searchTerm) ||
        url.shortUrl.toLowerCase().includes(searchTerm) ||
        (url.originalUrl && url.originalUrl.toLowerCase().includes(searchTerm))
    );
    
    renderUrls(filtered);
}

function isValidUrl(string) {
    try {
        const url = new URL(string);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch (_) {
        return false;
    }
}

function extractDomain(url) {
    try {
        const urlObj = new URL(url);
        return urlObj.hostname;
    } catch (_) {
        return '';
    }
}

function extractShortCode(url) {
    try {
        const urlObj = new URL(url);
        return urlObj.pathname.substring(1);
    } catch (_) {
        return '';
    }
}

function extractTitle(url) {
    try {
        const urlObj = new URL(url);
        return urlObj.hostname.replace('www.', '');
    } catch (_) {
        return url.substring(0, 30) + '...';
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function showLoading(show) {
    const loadingMsg = document.getElementById('loadingMsg');
    if (loadingMsg) {
        loadingMsg.style.display = show ? 'block' : 'none';
    }
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.disabled = show;
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

function showToast(message) {
    const existing = document.querySelector('.copy-toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'copy-toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 2000);
}

function copyUrl(index) {
    const url = urls[index];
    if (url) {
        navigator.clipboard.writeText(url.shortUrl).then(() => {
            showToast('✅ URL copiada');
        }).catch(err => {
            const textArea = document.createElement('textarea');
            textArea.value = url.shortUrl;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast('✅ URL copiada');
        });
    }
}

// Función de eliminación corregida para multidominio
async function deleteUrl(index) {
    const url = urls[index];
    if (!url) return;
    
    const deleteBtn = document.querySelector(`.btn-delete[data-index="${index}"]`);
    if (!deleteBtn) return;
    
    const originalContent = deleteBtn.innerHTML;
    
    if (deleteBtn.classList.contains('confirm-delete')) {
        const shortCode = extractShortCode(url.shortUrl);
        
        if (shortCode) {
            try {
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '⏳';
                
                // IMPORTANTE: Usar el servidor principal para la API
                const apiDomain = await API_CONFIG.getApiDomain();
                
                const response = await fetch(`https://${apiDomain}/api/delete-url.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        code: shortCode,
                        short_code: shortCode
                    })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        console.log('URL eliminada del servidor');
                    }
                }
            } catch (error) {
                console.log('Eliminando solo localmente');
            }
        }
        
        // Eliminar localmente
        urls.splice(index, 1);
        await chrome.storage.local.set({ urls: urls });
        renderUrls();
        updateStats();
        showToast('🗑️ URL eliminada');
        
    } else {
        // Mostrar confirmación
        deleteBtn.classList.add('confirm-delete');
        deleteBtn.innerHTML = '✓?';
        deleteBtn.title = 'Click para confirmar';
        
        setTimeout(() => {
            const btn = document.querySelector(`.btn-delete[data-index="${index}"]`);
            if (btn && !btn.disabled && btn.classList.contains('confirm-delete')) {
                btn.classList.remove('confirm-delete');
                btn.innerHTML = originalContent;
                btn.title = 'Eliminar';
            }
        }, 3000);
    }
}