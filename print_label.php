<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Label</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        .label {
            width: 300px;
            border: 1px dashed #ccc;
            padding: 10px;
            margin: 10px;
            text-align: center;
            display: inline-block;
            page-break-inside: avoid;
        }
        @media print {
            .no-print { display: none; }
            .label { border: none; }
        }
    </style>
</head>
<body onload="render()">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
        <hr>
    </div>
    
    <?php
    $code = $_GET['code'] ?? '123456';
    $name = $_GET['name'] ?? 'Item Name';
    $count = $_GET['count'] ?? 1;
    
    for($i=0; $i<$count; $i++):
    ?>
    <div class="label">
        <h3 style="margin: 0; font-size: 16px;"><?= htmlspecialchars($name) ?></h3>
        <svg class="barcode"
            jsbarcode-format="CODE128"
            jsbarcode-value="<?= htmlspecialchars($code) ?>"
            jsbarcode-textmargin="0"
            jsbarcode-fontoptions="bold">
        </svg>
    </div>
    <?php endfor; ?>

    <script>
        function render() {
            JsBarcode(".barcode").init();
        }
    </script>
</body>
</html>
