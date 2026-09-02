<?php
// add_customer.php - Register New Customer with Interactive Step Progress Wizard
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$pdo = get_db_connection();
$error = '';

// Fetch all active collectors for assignment
$stmtCol = $pdo->query("SELECT id, full_name FROM users WHERE role = 'collector' AND is_active = 1 ORDER BY full_name ASC");
$collectors = $stmtCol->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $collectorId = !empty($_POST['assigned_collector_id']) ? (int)$_POST['assigned_collector_id'] : null;
    $dailyAmount = (float)($_POST['daily_amount'] ?? 0);

    if (empty($fullName) || empty($phone)) {
        $error = 'Customer name and phone number are required.';
    } elseif ($dailyAmount <= 0) {
        $error = 'Please enter a valid agreed contribution amount (e.g. GH₵ 20, 50, 100).';
    } else {
        try {
            $pdo->beginTransaction();

            // Generate next account number safely using MAX(id)
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM customers")->fetchColumn();
            $accountNumber = 'ACC-' . str_pad($maxId + 1001, 4, '0', STR_PAD_LEFT);

            // Insert Customer
            $stmt = $pdo->prepare("
                INSERT INTO customers (account_number, full_name, phone, location, assigned_collector_id, change_balance) 
                VALUES (?, ?, ?, ?, ?, 0.00)
            ");
            $stmt->execute([$accountNumber, $fullName, $phone, $location, $collectorId]);
            $customerId = $pdo->lastInsertId();

            // Create initial 31-Space Susu Card
            $stmtCard = $pdo->prepare("
                INSERT INTO susu_cards (customer_id, card_number, daily_amount, total_spaces, spaces_filled, total_saved, status) 
                VALUES (?, 1, ?, 31, 0, 0.00, 'active')
            ");
            $stmtCard->execute([$customerId, $dailyAmount]);
            $cardId = $pdo->lastInsertId();

            if ($collectorId) {
                create_notification(
                    $collectorId,
                    'customer_assigned',
                    "New Client Assigned",
                    "New customer '{$fullName}' ({$accountNumber}) was assigned to your collection route.",
                    "collector_dashboard.php"
                );
            }

            $pdo->commit();

            set_flash_message('success', "Customer '{$fullName}' registered successfully with Account #{$accountNumber}!");
            header("Location: view_card.php?id={$cardId}");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Add Customer";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-steel_azure font-heading">Register New Customer</h1>
            <p class="text-xs text-slate-500 mt-0.5">Follow the 3 steps to register a client profile and activate their 31-space Susu card.</p>
        </div>
        <a href="customers.php" class="text-xs font-bold text-cornflower_ocean hover:text-steel_azure transition">
            &larr; Back to Directory
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Interactive Registration Form & Step Wizard -->
    <form id="add_customer_form" method="POST" action="add_customer.php" class="bg-white rounded-2xl border border-silver-600 shadow-sm p-5 sm:p-7 space-y-6">
        
        <!-- Step Progress Indicator (Interactive Wizard Progress) -->
        <div class="step-progress mb-6">
            <!-- Step 1 Dot -->
            <div id="step_dot_item_1" class="step-progress-item flex-col items-center text-center cursor-pointer" onclick="goToStep(1)">
                <div id="step_dot_1" class="step-progress-dot active">1</div>
                <div id="step_lbl_1" class="step-progress-label text-steel_azure font-extrabold">Client Info</div>
            </div>
            <div id="step_line_1" class="step-progress-line"></div>

            <!-- Step 2 Dot -->
            <div id="step_dot_item_2" class="step-progress-item flex-col items-center text-center cursor-pointer" onclick="validateAndGoToStep(2)">
                <div id="step_dot_2" class="step-progress-dot upcoming">2</div>
                <div id="step_lbl_2" class="step-progress-label">Collector</div>
            </div>
            <div id="step_line_2" class="step-progress-line"></div>

            <!-- Step 3 Dot -->
            <div id="step_dot_item_3" class="step-progress-item flex-col items-center text-center cursor-pointer" onclick="validateAndGoToStep(3)">
                <div id="step_dot_3" class="step-progress-dot upcoming">3</div>
                <div id="step_lbl_3" class="step-progress-label">Susu Card</div>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- STEP 1: CLIENT INFORMATION                              -->
        <!-- ======================================================= -->
        <div id="step_section_1" class="step-section space-y-5">
            <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-xl">
                <div class="flex items-center gap-2 text-steel_azure font-extrabold text-sm mb-1">
                    <span>👤</span> Step 1: Client Personal Details
                </div>
                <p class="text-xs text-slate-600">Enter the customer's full name, mobile phone, and business or market stall location.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="full_name" class="block text-xs font-bold text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" id="full_name" name="full_name" required autofocus
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm transition"
                           placeholder="e.g. Esi Mensah">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                        <input type="tel" id="phone" name="phone" required
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm transition"
                               placeholder="e.g. 0244123456">
                    </div>

                    <div>
                        <label for="location" class="block text-xs font-bold text-slate-700 mb-1">Location / Market Stall</label>
                        <input type="text" id="location" name="location"
                               value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm transition"
                               placeholder="e.g. Makola Market, Shed 4">
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-silver-600/60 flex items-center justify-between">
                <a href="customers.php" class="btn-touch bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs font-bold px-4 py-2.5">
                    Cancel
                </a>
                <button type="button" onclick="validateAndGoToStep(2)" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs sm:text-sm font-bold px-5 py-2.5 shadow-sm transition flex items-center gap-1.5">
                    <span>Continue to Step 2: Collector</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- STEP 2: FIELD COLLECTOR ASSIGNMENT                      -->
        <!-- ======================================================= -->
        <div id="step_section_2" class="step-section hidden space-y-5">
            <div class="p-4 bg-purple-50/70 border border-purple-100 rounded-xl">
                <div class="flex items-center gap-2 text-purple-900 font-extrabold text-sm mb-1">
                    <span>🏃</span> Step 2: Route & Field Collector Assignment
                </div>
                <p class="text-xs text-slate-600">Assign this customer to an active field agent who will visit their shop and collect daily Susu payments.</p>
            </div>

            <div>
                <label for="assigned_collector_id" class="block text-xs font-bold text-slate-700 mb-1">Select Field Collector</label>
                <select id="assigned_collector_id" name="assigned_collector_id"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm transition bg-white">
                    <option value="">-- Unassigned (Office Collects Directly) --</option>
                    <?php foreach ($collectors as $col): ?>
                        <option value="<?= $col['id'] ?>" <?= (isset($_POST['assigned_collector_id']) && (int)$_POST['assigned_collector_id'] === (int)$col['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($col['full_name']) ?> (Field Agent)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="helper-text mt-2">The selected collector will automatically receive a real-time mobile notification and will see this client in their field route.</p>
            </div>

            <div class="pt-5 border-t border-silver-600/60 flex items-center justify-between">
                <button type="button" onclick="goToStep(1)" class="btn-touch bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs font-bold px-4 py-2.5 flex items-center gap-1.5">
                    <span>&larr;</span>
                    <span>Back to Client Info</span>
                </button>
                <button type="button" onclick="goToStep(3)" class="btn-touch bg-steel_azure hover:bg-steel_azure-400 text-white text-xs sm:text-sm font-bold px-5 py-2.5 shadow-sm transition flex items-center gap-1.5">
                    <span>Continue to Step 3: Susu Card</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- STEP 3: INITIAL 31-SPACE SUSU CARD & CONFIRMATION       -->
        <!-- ======================================================= -->
        <div id="step_section_3" class="step-section hidden space-y-5">
            <div class="p-4 bg-emerald-50/70 border border-emerald-100 rounded-xl">
                <div class="flex items-center gap-2 text-emerald-900 font-extrabold text-sm mb-1">
                    <span>📋</span> Step 3: Susu Savings Card & Plan Setup
                </div>
                <p class="text-xs text-slate-600">Set the daily contribution amount per space. Each card contains 31 spaces.</p>
            </div>

            <div>
                <label for="daily_amount" class="block text-xs font-bold text-slate-700 mb-1">Agreed Contribution Amount per Space <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 font-bold text-xs">GH₵</span>
                    <input type="number" step="0.50" min="1" id="daily_amount" name="daily_amount" required
                           value="<?= htmlspecialchars($_POST['daily_amount'] ?? '20.00') ?>"
                           oninput="updateReviewSummary()"
                           class="w-full pl-12 pr-3.5 py-2.5 rounded-xl border border-silver-600 focus:border-steel_azure focus:ring-2 focus:ring-cornflower_ocean-800 outline-none text-xs sm:text-sm font-bold text-slate-800 transition">
                </div>

                <!-- Preset Quick Buttons -->
                <div class="flex items-center gap-2 mt-2.5">
                    <span class="text-[11px] text-slate-500 font-bold">Quick Select:</span>
                    <button type="button" onclick="setDailyAmount(10)" class="px-2.5 py-1 rounded-lg bg-platinum-800 hover:bg-silver-600 text-slate-700 text-xs font-bold transition">GH₵ 10</button>
                    <button type="button" onclick="setDailyAmount(20)" class="px-2.5 py-1 rounded-lg bg-platinum-800 hover:bg-silver-600 text-slate-700 text-xs font-bold transition">GH₵ 20</button>
                    <button type="button" onclick="setDailyAmount(50)" class="px-2.5 py-1 rounded-lg bg-platinum-800 hover:bg-silver-600 text-slate-700 text-xs font-bold transition">GH₵ 50</button>
                    <button type="button" onclick="setDailyAmount(100)" class="px-2.5 py-1 rounded-lg bg-platinum-800 hover:bg-silver-600 text-slate-700 text-xs font-bold transition">GH₵ 100</button>
                </div>
            </div>

            <!-- Review Summary Card -->
            <div class="bg-platinum-800 border border-silver-600/80 rounded-xl p-4 space-y-2.5">
                <div class="text-xs font-black uppercase text-slate-400 tracking-wider">Registration Summary</div>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-slate-400 font-medium">Customer:</span>
                        <div id="summary_name" class="font-bold text-slate-800 truncate">—</div>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium">Phone:</span>
                        <div id="summary_phone" class="font-bold text-slate-800 truncate">—</div>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium">Assigned Collector:</span>
                        <div id="summary_collector" class="font-bold text-steel_azure truncate">Unassigned</div>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium">31-Space Target:</span>
                        <div id="summary_target" class="font-bold text-emerald-600">GH₵ 620.00</div>
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-silver-600/60 flex items-center justify-between">
                <button type="button" onclick="goToStep(2)" class="btn-touch bg-white text-slate-600 hover:bg-platinum-800 border border-silver-600 text-xs font-bold px-4 py-2.5 flex items-center gap-1.5">
                    <span>&larr;</span>
                    <span>Back to Collector</span>
                </button>
                <button type="submit" class="btn-touch bg-pumpkin_spice hover:bg-pumpkin_spice-400 text-white text-xs sm:text-sm font-extrabold px-6 py-2.5 shadow-md hover:shadow-lg transition flex items-center gap-2">
                    <span>✓</span>
                    <span>Save & Open Susu Card</span>
                </button>
            </div>
        </div>

    </form>

</div>

<script>
let currentStep = 1;

function goToStep(step) {
    if (step < 1 || step > 3) return;

    // Hide all steps
    document.getElementById('step_section_1').classList.add('hidden');
    document.getElementById('step_section_2').classList.add('hidden');
    document.getElementById('step_section_3').classList.add('hidden');

    // Show target step
    document.getElementById(`step_section_${step}`).classList.remove('hidden');
    currentStep = step;

    // Update Progress Indicator
    updateProgressUI(step);

    if (step === 3) {
        updateReviewSummary();
    }

    // Scroll smoothly to form top on mobile
    document.getElementById('add_customer_form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function validateAndGoToStep(targetStep) {
    if (targetStep === 2 || targetStep === 3) {
        // Validate Step 1 first
        const nameField = document.getElementById('full_name');
        const phoneField = document.getElementById('phone');

        let valid = true;
        if (!nameField.value.trim()) {
            nameField.classList.add('field-error', 'field-shake');
            valid = false;
            setTimeout(() => nameField.classList.remove('field-shake'), 400);
        } else {
            nameField.classList.remove('field-error');
        }

        if (!phoneField.value.trim()) {
            phoneField.classList.add('field-error', 'field-shake');
            valid = false;
            setTimeout(() => phoneField.classList.remove('field-shake'), 400);
        } else {
            phoneField.classList.remove('field-error');
        }

        if (!valid) {
            goToStep(1);
            return;
        }
    }

    goToStep(targetStep);
}

function updateProgressUI(step) {
    const dot1 = document.getElementById('step_dot_1');
    const dot2 = document.getElementById('step_dot_2');
    const dot3 = document.getElementById('step_dot_3');

    const lbl1 = document.getElementById('step_lbl_1');
    const lbl2 = document.getElementById('step_lbl_2');
    const lbl3 = document.getElementById('step_lbl_3');

    const line1 = document.getElementById('step_line_1');
    const line2 = document.getElementById('step_line_2');

    // Reset base classes
    [dot1, dot2, dot3].forEach(d => d.className = 'step-progress-dot upcoming');
    [lbl1, lbl2, lbl3].forEach(l => l.className = 'step-progress-label');
    [line1, line2].forEach(l => l.className = 'step-progress-line');

    dot1.innerHTML = '1';
    dot2.innerHTML = '2';
    dot3.innerHTML = '3';

    if (step === 1) {
        dot1.className = 'step-progress-dot active';
        lbl1.className = 'step-progress-label text-steel_azure font-extrabold';
    } else if (step === 2) {
        dot1.className = 'step-progress-dot completed';
        dot1.innerHTML = '✓';
        lbl1.className = 'step-progress-label text-emerald-600 font-bold';
        line1.className = 'step-progress-line completed';

        dot2.className = 'step-progress-dot active';
        lbl2.className = 'step-progress-label text-steel_azure font-extrabold';
    } else if (step === 3) {
        dot1.className = 'step-progress-dot completed';
        dot1.innerHTML = '✓';
        lbl1.className = 'step-progress-label text-emerald-600 font-bold';
        line1.className = 'step-progress-line completed';

        dot2.className = 'step-progress-dot completed';
        dot2.innerHTML = '✓';
        lbl2.className = 'step-progress-label text-emerald-600 font-bold';
        line2.className = 'step-progress-line completed';

        dot3.className = 'step-progress-dot active';
        lbl3.className = 'step-progress-label text-steel_azure font-extrabold';
    }
}

function setDailyAmount(amount) {
    const input = document.getElementById('daily_amount');
    input.value = parseFloat(amount).toFixed(2);
    updateReviewSummary();
}

function updateReviewSummary() {
    const name = document.getElementById('full_name').value.trim() || '—';
    const phone = document.getElementById('phone').value.trim() || '—';
    const collectorSelect = document.getElementById('assigned_collector_id');
    const collectorText = collectorSelect.selectedIndex > 0 ? collectorSelect.options[collectorSelect.selectedIndex].text : 'Unassigned (Office)';
    const daily = parseFloat(document.getElementById('daily_amount').value) || 0;
    const target = daily * 31;

    document.getElementById('summary_name').textContent = name;
    document.getElementById('summary_phone').textContent = phone;
    document.getElementById('summary_collector').textContent = collectorText;
    document.getElementById('summary_target').textContent = 'GH₵ ' + target.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
