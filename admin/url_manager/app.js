let urls = [];

document.addEventListener('DOMContentLoaded', function() {
    loadUrls();
    setupEventListeners();
});

function extractDomain(url) {
    try {
        return new URL(url).hostname;
    } catch (_) {
        return '';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function setupEventListeners() {
    document.getElementById('toggleAddBtn').addEventListener('click', toggleAddForm);
    document.getElementById('saveUrlBtn').addEventListener('click', addUrl);
    document.getElementById('cancelBtn').addEventListener('click', toggleAddForm);
    document.getElementById('searchInput').addEventListener('input', filterUrls);
    document.getElementById('importApiBtn').addEventListener('click', importFromAPI);
    document.getElementById('exportBtn').addEventListener('click', exportUrls);
    document.getElementById('clearAllBtn').addEventListener('click', clearAllUrls);
}

function loadUrls() {
    const stored = localStorage.getItem('urlShortenerUrls');
    urls = stored ? JSON.parse(stored) : [];
    renderUrls();
    updateStats();
}

function saveUrls() {
    localStorage.setItem('urlShortenerUrls', JSON.stringify(urls));
}

function updateStats() {
    const totalUrls = urls.length;
    document.getElementById('stats').textContent = `📊 ${totalUrls} URLs guardadas`;
}

function renderUrls() {
    const list = document.getElementById("urlList");
    
    if (urls.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No hay URLs guardadas</h3>
                <p>Agrega tu primera URL corta o importa desde 0ln.eu</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = urls.map((url, index) => {
        const favicon = url.favicon || `https://www.google.com/s2/favicons?domain=${extractDomain(url.originalUrl || url.shortUrl)}`;
        const domain = extractDomain(url.shortUrl);
        
        return `
            <div class="url-item" onclick="openUrl(${index})">
                <div class="url-actions" onclick="event.stopPropagation()">
                    <button class="btn-action" onclick="copyUrl(${index})" title="Copiar URL">📋</button>
                    <button class="btn-action delete" onclick="deleteUrl(${index})" title="Eliminar">🗑️</button>
                </div>
                
                <div class="url-header">
                    <img src="${favicon}" class="favicon" onerror="this.style.display='none'" style="width: 20px; height: 20px; border-radius: 4px; margin-right: 12px;" />
                    <div class="url-title" style="font-weight: 600; color: #2c3e50; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 16px;">${escapeHtml(url.title)}</div>
                </div>
                
                <div class="url-short" style="color: #667eea; font-size: 14px; font-family: 'Courier New', monospace; margin: 6px 0; display: flex; align-items: center; gap: 8px; font-weight: 500;">
                    🔗 ${escapeHtml(url.shortUrl)}
                    <span class="domain-tag" style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">${escapeHtml(domain)}</span>
                </div>
                
                ${url.originalUrl ? `
                    <div class="url-original" style="color: #7f8c8d; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 6px 0;" title="${escapeHtml(url.originalUrl)}">
                        ➡️ ${escapeHtml(url.originalUrl)}
                    </div>
                ` : ''}
                
                ${url.date ? `
                    <div class="url-date" style="color: #aaa; font-size: 11px; margin-top: 8px;">
                        📅 ${new Date(url.date).toLocaleDateString('es-ES', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        })}
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

function toggleAddForm() {
    const form = document.getElementById('addForm');
    const btn = document.getElementById('toggleAddBtn');
    
    if (form.classList.contains('show')) {
        form.classList.remove('show');
        btn.textContent = '➕ Agregar URL';
    } else {
        form.classList.add('show');
        btn.textContent = '❌ Cancelar';
        document.getElementById('shortUrlInput').focus();
    }
}

function addUrl() {
    const shortUrl = document.getElementById('shortUrlInput').value.trim();
    const title = document.getElementById('titleInput').value.trim();
    
    if (!shortUrl) {
        alert('Por favor ingresa una URL');
        return;
    }
    
    const newUrl = {
        shortUrl: shortUrl,
        title: title || shortUrl,
        date: new Date().toISOString()
    };
    
    urls.unshift(newUrl);
    saveUrls();
    
    document.getElementById('shortUrlInput').value = '';
    document.getElementById('titleInput').value = '';
    toggleAddForm();
    renderUrls();
    updateStats();
    showToast('✅ URL guardada');
}

function openUrl(index) {
    window.open(urls[index].shortUrl, '_blank');
}

function copyUrl(index) {
    navigator.clipboard.writeText(urls[index].shortUrl).then(() => {
        showToast('✅ URL copiada');
    });
}

function deleteUrl(index) {
    if (confirm('¿Eliminar esta URL?')) {
        urls.splice(index, 1);
        saveUrls();
        renderUrls();
        updateStats();
        showToast('🗑️ URL eliminada');
    }
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function importFromAPI() {
    const btn = document.getElementById("importApiBtn");
    btn.disabled = true;
    btn.textContent = "⏳ Importando...";
    
    fetch("/api/my-urls.php", {
        credentials: "include",
        mode: "cors"
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("Error en servidor: " + response.status);
        }
        return response.json();
    })
    .then(apiUrls => {
        if (!Array.isArray(apiUrls)) {
            throw new Error("Formato de respuesta inválido");
        }
        
        let imported = 0;
        for (const apiUrl of apiUrls) {
            const shortUrl = apiUrl.short_url || `https://0ln.eu/${apiUrl.short_code}`;
            
            if (!urls.find(u => u.shortUrl === shortUrl)) {
                urls.unshift({
                    shortUrl: shortUrl,
                    title: apiUrl.title || apiUrl.short_code || "Sin título",
                    originalUrl: apiUrl.original_url || null,
                    date: apiUrl.created_at || new Date().toISOString()
                });
                imported++;
            }
        }
        
        if (imported > 0) {
            saveUrls();
            renderUrls();
            updateStats();
            showToast(`✅ ${imported} URLs importadas de 0ln.eu`);
        } else {
            showToast("ℹ️ No hay URLs nuevas para importar");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        showToast("❌ Error al importar: " + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = "📥 Importar de 0ln.eu";
    });
}

function exportUrls() {
    if (urls.length === 0) {
        showToast('No hay URLs para exportar');
        return;
    }
    
    const data = JSON.stringify({urls: urls}, null, 2);
    const blob = new Blob([data], {type: 'application/json'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'urls_backup.json';
    link.click();
    showToast('✅ URLs exportadas');
}

function clearAllUrls() {
    if (confirm('¿Eliminar todas las URLs?')) {
        urls = [];
        saveUrls();
        renderUrls();
        updateStats();
        showToast('🗑️ URLs eliminadas');
    }
}

function filterUrls(e) {
    const searchTerm = e.target.value.toLowerCase();
    const filtered = searchTerm ? 
        urls.filter(url => url.title.toLowerCase().includes(searchTerm) || url.shortUrl.toLowerCase().includes(searchTerm)) : 
        urls;
    renderUrls(filtered);
}
