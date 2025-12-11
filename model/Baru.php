<?php
// Baru.php — Menu +Baru (Create New)
// Versi premium UI by ChatGPT
?>
<style>
    .gd-dropdown {
        width: 290px;
        background: #fff;
        border-radius: 14px;
        padding: 6px 0;
        box-shadow: 0 10px 32px rgba(0,0,0,0.12);
        animation: baruFadeIn .18s ease;
        font-family: "Inter", "Poppins", sans-serif;
    }

    @keyframes baruFadeIn {
        from { opacity: 0; transform: translateY(-8px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .gd-item {
        padding: 12px 16px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        cursor: pointer;
        border-radius: 10px;
        transition: .18s ease;
    }

    .gd-item:hover {
        background: rgba(99,102,241,0.06);
        transform: translateX(3px);
    }

    .gd-icon-wrap {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #eef2ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #6366f1;
        flex-shrink: 0;
    }

    .gd-text {
        flex: 1;
    }

    .gd-title {
        font-size: 15px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 3px;
    }

    .gd-desc {
        font-size: 12px;
        color: #6b7280;
        line-height: 1.3;
    }

    hr {
        border: none;
        border-top: 1px solid #e5e7eb;
        margin: 6px 0;
    }
</style>

<div class="gd-dropdown">

    <!-- FOLDER BARU -->
    <div class="gd-item" data-modal="modalFolder">
        <div class="gd-icon-wrap"><i class="fa fa-folder-plus"></i></div>
        <div class="gd-text">
            <div class="gd-title">Folder Baru</div>
            <div class="gd-desc">Buat direktori untuk mengelompokkan file</div>
        </div>
    </div>

    <!-- UPLOAD FILE -->
    <div class="gd-item" data-modal="modalUploadFile">
        <div class="gd-icon-wrap"><i class="fa fa-file-upload"></i></div>
        <div class="gd-text">
            <div class="gd-title">Upload File</div>
            <div class="gd-desc">Unggah file dari perangkat Anda</div>
        </div>
    </div>

    <!-- UPLOAD FOLDER -->
    <div class="gd-item" data-modal="modalUploadFolder">
        <div class="gd-icon-wrap"><i class="fa fa-folder-open"></i></div>
        <div class="gd-text">
            <div class="gd-title">Upload Folder</div>
            <div class="gd-desc">Unggah folder ZIP atau banyak file sekaligus</div>
        </div>
    </div>

</div>

<!-- Event JS di-handle oleh index.php (modal) -->
