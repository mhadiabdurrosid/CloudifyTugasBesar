<link rel="stylesheet" href="/Cloudify/Cloudify/asset/csspages/Menu-Utama.css">

<style>
    /* Tambahan CSS kecil untuk perapihan */
    .delete-btn 
    { margin-left:auto; color:#e53935; 
    text-decoration:none; font-weight:600; }
    .header-icons button 
    { cursor: pointer; margin-left: 5px; }
</style>

<header class="app-header">
    <h1>Selamat Datang di Cloudify</h1>
    <div class="header-icons" style="margin-left:auto">
        <button id="btnList" onclick="setView('list')" aria-label="List view"><i class="fas fa-list"></i></button>
        <button id="btnGrid" onclick="setView('grid')" aria-label="Grid view"><i class="fas fa-th-large"></i></button>
    
    </div>
</header>

<div id="listHeader" class="list-header hidden">
    <span>Nama File</span>
    <span>Status</span>
    <span>Aksi</span>
</div>

<div class="file-scroll-area">
    
    <div id="content-area">
        <?php include __DIR__ . '/components/file_list.php'; ?>
    </div>

</div>

<script src="asset/jspages/ViewListGrid.js"></script>

<script>
// Logic Realtime yang Lebih Efisien
(function(){
    const container = document.getElementById('content-area');
    let isFetching = false;

    async function refreshContent(){
        if(isFetching) return; // Mencegah request bertumpuk
        isFetching = true;

        try {
            // Mengarah ke file komponen view, BUKAN model
            const response = await fetch('pages/components/file_list.php'); 
            if(response.ok) {
                const html = await response.text();
                // Hanya update jika konten berbeda (opsional, butuh library diffing, tapi ini basicnya)
                container.innerHTML = html; 
                
                // Re-apply view mode (List/Grid) setelah refresh AJAX
                // Asumsi fungsi ini ada di ViewListGrid.js Anda
                if (typeof applyViewMode === "function") applyViewMode(); 
            }
        } catch (err) {
            console.error('Gagal memuat update:', err);
        } finally {
            isFetching = false;
        }
    }

// Polling setiap 10 detik
setInterval(refreshContent, 10000);
})();

document.getElementById('btnSyncUploads').addEventListener('click', async function(){
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Syncing...';
    try {
        const response = await fetch('model/sync_files.php');
        if (response.ok) {
            const result = await response.json();
            alert(`Sync Complete. Inserted: ${result.inserted}, Skipped: ${result.skipped}`);
            refreshContent();
        } else {
            alert('Sync failed: Server error');
        }
    } catch (e) {
        alert('Sync failed: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Sync Uploads';
    }
});

// Helper sederhana untuk view toggle (Opsional jika ViewListGrid.js belum handle ini)
function setView(mode) {
    const wrapper = document.getElementById('fileWrapper');
    const header = document.getElementById('listHeader');
    
    if(mode === 'list') {
        wrapper.classList.remove('grid-view');
        wrapper.classList.add('list-view');
        header.classList.remove('hidden');
    } else {
        wrapper.classList.remove('list-view');
        wrapper.classList.add('grid-view');
        header.classList.add('hidden');
    }
    // Simpan preferensi user ke localStorage
    localStorage.setItem('viewMode', mode);
}

// Load preferensi saat awal
const savedMode = localStorage.getItem('viewMode') || 'grid';
setView(savedMode);
</script>