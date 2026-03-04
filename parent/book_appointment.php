<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// ── Mark notification as read (AJAX or form post) ──
if(isset($_POST['dismiss_notification']) && isset($_POST['notification_id'])) {
    $notif_id = intval($_POST['notification_id']);
    $uid      = $_SESSION['user_id'];
    mysqli_query($connection,
        "UPDATE notifications SET is_read=1 WHERE notification_id='$notif_id' AND user_id='$uid'");
    header("location: book_appointment.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Get parent data
$query_parent  = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data   = mysqli_fetch_assoc($result_parent);
$parent_id     = $parent_data['parent_id'] ?? "";

// DEBUG
if(empty($parent_id)) {
    die("<h2 style='color:red'>DEBUG: parent_id not found!<br>user_id from session = '$user_id'<br>DB Error = " . mysqli_error($connection) . "<br>Session = <pre>" . print_r($_SESSION, true) . "</pre></h2>");
}

// Get parent's active children
$query_children  = "SELECT * FROM children WHERE parent_id = '$parent_id' AND is_active = 1 ORDER BY full_name";
$result_children = mysqli_query($connection, $query_children);
$children_count  = mysqli_num_rows($result_children);

// Get verified + active hospitals
$query_hospitals  = "SELECT * FROM hospitals WHERE is_active = 1 AND is_verified = 1 ORDER BY hospital_name";
$result_hospitals = mysqli_query($connection, $query_hospitals);
$hospitals_count  = mysqli_num_rows($result_hospitals);

// Get active vaccines
$query_vaccines  = "SELECT * FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name";
$result_vaccines = mysqli_query($connection, $query_vaccines);

// Flash message
$msg      = $_SESSION['msg']      ?? '';
$msg_type = $_SESSION['msg_type'] ?? '';
unset($_SESSION['msg'], $_SESSION['msg_type']);

// ── Fetch unread approval notifications for this parent ──
$approval_alerts = [];
$q_alerts = "SELECT n.notification_id, n.message, n.related_id, n.created_at,
                    vb.confirmation_code, vb.appointment_date, vb.appointment_time,
                    c.full_name as child_name, v.vaccine_name, h.hospital_name
             FROM notifications n
             JOIN appointment_requests ar ON n.related_id = ar.request_id
             JOIN vaccination_bookings vb ON vb.request_id = ar.request_id
             JOIN children c   ON ar.child_id  = c.child_id
             JOIN vaccines v   ON ar.vaccine_id = v.vaccine_id
             JOIN hospitals h  ON ar.hospital_id = h.hospital_id
             WHERE n.user_id = '$user_id'
               AND n.notification_type = 'appointment_approved'
               AND n.is_read = 0
             ORDER BY n.created_at DESC";
$r_alerts = mysqli_query($connection, $q_alerts);
if($r_alerts){
    while($row = mysqli_fetch_assoc($r_alerts)){
        $approval_alerts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — VacciCare</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',Arial,sans-serif; background:#f0f4ff; color:#1a1a2e; }

        /* NAVBAR */
        .navbar {
            background:#fff; border-bottom:2px solid #e8eeff;
            padding:0 35px; display:flex; justify-content:space-between;
            align-items:center; height:68px;
            box-shadow:0 2px 16px rgba(59,130,246,0.08);
            position:sticky; top:0; z-index:100;
        }
        .navbar-brand { display:flex; align-items:center; gap:10px; }
        .navbar-brand .brand-icon {
            width:40px; height:40px;
            background:linear-gradient(135deg,#3b82f6,#1d4ed8);
            border-radius:10px; display:flex; align-items:center;
            justify-content:center; font-size:20px;
        }
        .navbar-brand h2 { font-size:20px; font-weight:700; color:#1d4ed8; }
        .navbar-links { display:flex; align-items:center; gap:6px; }
        .navbar-links a {
            color:#4b6cb7; text-decoration:none; padding:8px 14px;
            border-radius:8px; font-size:13.5px; font-weight:500; transition:all .2s;
        }
        .navbar-links a:hover  { background:#eff6ff; color:#1d4ed8; }
        .navbar-links a.active { background:#eff6ff; color:#1d4ed8; font-weight:600; }
        .navbar-links a.logout { background:#fee2e2; color:#dc2626; }
        .navbar-links a.logout:hover { background:#fecaca; }

        /* LAYOUT */
        .page-wrapper { max-width:820px; margin:32px auto; padding:0 24px; }

        /* BACK LINK */
        .back-link {
            display:inline-flex; align-items:center; gap:6px;
            color:#1d4ed8; text-decoration:none; font-size:14px;
            font-weight:600; margin-bottom:20px; padding:8px 14px;
            background:#fff; border:1px solid #e8eeff; border-radius:8px;
            transition:all .2s; box-shadow:0 1px 4px rgba(59,130,246,.07);
        }
        .back-link:hover { background:#eff6ff; transform:translateX(-3px); }

        /* PAGE BANNER */
        .page-banner {
            background:linear-gradient(135deg,#1d4ed8 0%,#3b82f6 60%,#60a5fa 100%);
            border-radius:18px; padding:28px 36px; margin-bottom:24px;
            color:#fff; display:flex; align-items:center; justify-content:space-between;
            box-shadow:0 8px 32px rgba(59,130,246,.3); position:relative; overflow:hidden;
        }
        .page-banner::before {
            content:''; position:absolute; top:-40px; right:-40px;
            width:180px; height:180px; background:rgba(255,255,255,.08); border-radius:50%;
        }
        .banner-text { position:relative; z-index:1; }
        .banner-text h2 { font-size:21px; font-weight:700; margin-bottom:4px; }
        .banner-text p  { font-size:13px; opacity:.85; }
        .banner-icon { font-size:48px; position:relative; z-index:1; opacity:.9; }

        /* ALERT */
        .alert {
            padding:13px 18px; border-radius:10px; margin-bottom:18px;
            font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px;
        }
        .alert-success { background:#dcfce7; border:1px solid #86efac; color:#166534; }
        .alert-error   { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }

        /* APPROVAL ALERT BANNER */
        .approval-alert {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 2px solid #6ee7b7;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 18px;
            position: relative;
            overflow: hidden;
            animation: slideDown .4s ease;
        }
        .approval-alert::before {
            content: '';
            position: absolute; top:0; left:0; bottom:0;
            width: 5px;
            background: linear-gradient(180deg, #10b981, #059669);
            border-radius: 14px 0 0 14px;
        }
        .approval-alert-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 12px;
        }
        .approval-alert-title {
            font-size: 15px; font-weight: 800; color: #065f46;
            display: flex; align-items: center; gap: 8px;
        }
        .approval-badge {
            background: #10b981; color: white;
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 20px;
            letter-spacing: .5px;
        }
        .approval-close {
            background: none; border: none; cursor: pointer;
            color: #6ee7b7; font-size: 18px; font-weight: 700;
            line-height: 1; padding: 0 4px;
        }
        .approval-close:hover { color: #065f46; }
        .approval-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px; margin-bottom: 14px;
        }
        .approval-item { background: white; border-radius: 9px; padding: 10px 13px; }
        .approval-item-label { font-size: 10.5px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .5px; }
        .approval-item-val   { font-size: 13.5px; font-weight: 700; color: #1a1a2e; margin-top: 3px; }
        .conf-code {
            display: inline-block;
            background: #1d4ed8; color: white;
            font-size: 13px; font-weight: 800;
            padding: 4px 14px; border-radius: 8px;
            letter-spacing: 1px; font-family: monospace;
        }
        .approval-dismiss-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #059669; color: white;
            padding: 9px 20px; border-radius: 9px;
            font-size: 13px; font-weight: 700;
            border: none; cursor: pointer; font-family: 'Inter', sans-serif;
            transition: all .2s; text-decoration: none;
        }
        .approval-dismiss-btn:hover { background: #047857; transform: translateY(-1px); }

        /* MAIN CARD */
        .form-card {
            background:#fff; border-radius:16px; border:1px solid #e8eeff;
            box-shadow:0 2px 12px rgba(59,130,246,.07); padding:36px;
        }

        /* SECTION TITLE */
        .section-title {
            font-size:12px; font-weight:700; color:#6b7280;
            text-transform:uppercase; letter-spacing:.7px;
            margin:28px 0 16px; padding-bottom:8px;
            border-bottom:1px solid #f1f5ff;
        }
        .section-title:first-child { margin-top:0; }

        /* FORM GRID */
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .form-group { margin-bottom:0; }
        .form-group.full { grid-column:1/-1; }

        /* LABELS */
        .field-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:7px; }
        .field-label .req { color:#ef4444; margin-left:2px; }

        /* INPUTS */
        input[type="date"], input[type="time"], select, textarea {
            width:100%; padding:11px 14px;
            border:1.5px solid #e8eeff; border-radius:10px;
            font-size:14px; font-family:'Inter',Arial,sans-serif;
            color:#1a1a2e; background:#fafbff; transition:all .2s; outline:none;
        }
        input[type="date"]:focus, input[type="time"]:focus, select:focus, textarea:focus {
            border-color:#3b82f6; background:#fff;
            box-shadow:0 0 0 3px rgba(59,130,246,.1);
        }
        textarea { min-height:88px; resize:vertical; }
        select {
            appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 14px center; padding-right:38px;
        }

        /* VACCINE INFO BOX */
        #vaccine-info-box {
            display:none; margin-top:10px;
            background:linear-gradient(135deg,#1d4ed8,#3b82f6);
            border-radius:10px; padding:12px 16px; color:#fff; font-size:13px; line-height:1.5;
        }
        #vaccine-info-box strong { display:block; font-size:14px; margin-bottom:4px; }

        /* TIME SLOTS */
        .time-slots { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .time-slot {
            padding:8px 14px; border:1.5px solid #e8eeff; border-radius:8px;
            font-size:13px; font-weight:500; color:#4b6cb7;
            cursor:pointer; transition:all .18s; background:#fafbff;
        }
        .time-slot:hover { border-color:#3b82f6; background:#eff6ff; color:#1d4ed8; }
        .time-slot.selected {
            background:linear-gradient(135deg,#1d4ed8,#3b82f6);
            color:#fff; border-color:#1d4ed8;
            box-shadow:0 3px 10px rgba(29,78,216,.25);
        }

        /* Time error */
        .time-error {
            display:none; color:#dc2626; font-size:12px;
            margin-top:6px; font-weight:500;
        }

        /* HINT */
        .dose-hint { font-size:12px; color:#9ca3af; margin-top:5px; }

        /* SUBMIT BUTTON */
        .btn-submit {
            width:100%; padding:14px;
            background:linear-gradient(135deg,#1d4ed8,#3b82f6);
            color:#fff; border:none; border-radius:10px;
            font-size:15px; font-weight:700; cursor:pointer;
            margin-top:28px; font-family:'Inter',Arial,sans-serif;
            box-shadow:0 4px 14px rgba(29,78,216,.25); transition:all .2s;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(29,78,216,.35); }
        .btn-submit:disabled { opacity:.6; cursor:not-allowed; transform:none; }

        /* EMPTY WARN */
        .empty-warn {
            text-align:center; padding:55px 30px; background:#fff;
            border-radius:16px; border:2px dashed #bfdbfe;
        }
        .empty-warn .ew-icon { font-size:52px; margin-bottom:14px; display:block; }
        .empty-warn h3 { font-size:18px; font-weight:700; color:#1a1a2e; margin-bottom:8px; }
        .empty-warn p  { color:#6b7280; font-size:14px; margin-bottom:20px; }
        .empty-warn a  {
            display:inline-flex; align-items:center; gap:6px;
            background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#fff;
            padding:11px 22px; border-radius:10px; text-decoration:none;
            font-size:14px; font-weight:600;
            box-shadow:0 4px 14px rgba(29,78,216,.25); transition:all .2s;
        }
        .empty-warn a:hover { transform:translateY(-2px); }

        @media(max-width:620px){
            .navbar { padding:0 16px; }
            .navbar-brand h2 { display:none; }
            .page-wrapper { padding:0 14px; }
            .form-row { grid-template-columns:1fr; }
            .form-group.full { grid-column:1; }
            .form-card { padding:20px; }
            .page-banner { flex-direction:column; gap:10px; }
            .banner-icon { display:none; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="navbar-brand">
    
        <h2>Parent_Panel</h2>
    </div>
    <div class="navbar-links">
        <a href="dashboard.php"> Dashboard</a>
        <a href="my_children.php"> My Children</a>
        <a href="book_appointment.php" class="active"> Book</a>
        <a href="my_requests.php"> My Requests</a>
        <a href="vaccinationhistory.php"> History</a>
        <a href="myprofile.php"> Profile</a>
        <a href="../logout.php" class="logout"> Logout</a>
    </div>
</div>

<div class="page-wrapper">

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

    <!-- BANNER -->
    <div class="page-banner">
        <div class="banner-text">
            <h2>📅 Book Appointment</h2>
            <p>Fill in the form below to request a vaccination appointment</p>
        </div>
        <div class="banner-icon">💉</div>
    </div>

    <!-- APPROVAL ALERTS — shown when hospital approves a request -->
    <?php foreach($approval_alerts as $alert): ?>
    <div class="approval-alert" id="alert-<?php echo $alert['notification_id']; ?>">
        <div class="approval-alert-header">
            <div class="approval-alert-title">
                Appointment Approved!
                <span class="approval-badge">NEW</span>
            </div>
            <button class="approval-close" onclick="dismissAlert(<?php echo $alert['notification_id']; ?>)" title="Dismiss">x</button>
        </div>
        <div class="approval-grid">
            <div class="approval-item">
                <div class="approval-item-label">Child</div>
                <div class="approval-item-val"><?php echo htmlspecialchars($alert['child_name']); ?></div>
            </div>
            <div class="approval-item">
                <div class="approval-item-label">Vaccine</div>
                <div class="approval-item-val"><?php echo htmlspecialchars($alert['vaccine_name']); ?></div>
            </div>
            <div class="approval-item">
                <div class="approval-item-label">Hospital</div>
                <div class="approval-item-val"><?php echo htmlspecialchars($alert['hospital_name']); ?></div>
            </div>
            <div class="approval-item">
                <div class="approval-item-label">Date</div>
                <div class="approval-item-val"><?php echo date('d M Y', strtotime($alert['appointment_date'])); ?></div>
            </div>
            <div class="approval-item">
                <div class="approval-item-label">Time</div>
                <div class="approval-item-val"><?php echo date('h:i A', strtotime($alert['appointment_time'])); ?></div>
            </div>
            <div class="approval-item">
                <div class="approval-item-label">Confirmation Code</div>
                <div class="approval-item-val"><span class="conf-code"><?php echo htmlspecialchars($alert['confirmation_code']); ?></span></div>
            </div>
        </div>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="dismiss_notification" value="1">
            <input type="hidden" name="notification_id" value="<?php echo $alert['notification_id']; ?>">
            <button type="submit" class="approval-dismiss-btn">
                Mark as Read & Confirm Visit
            </button>
        </form>
    </div>
    <?php endforeach; ?>

    <!-- FLASH MSG -->
    <?php if($msg): ?>
    <div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>">
        <?php echo $msg_type === 'success' ? '' : ''; ?>
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <?php if($children_count == 0): ?>
    <!-- NO CHILDREN -->
    <div class="empty-warn">
        <span class="ew-icon">👶</span>
        <h3>No Children Registered</h3>
        <p>Please add a child first before booking an appointment.</p>
        <a href="add_child.php">➕ Add Child Now</a>
    </div>

    <?php elseif($hospitals_count == 0): ?>
    <!-- NO HOSPITALS -->
    <div class="empty-warn">
        <span class="ew-icon">🏥</span>
        <h3>No Hospitals Available</h3>
        <p>No verified hospitals at the moment. Please try again later.</p>
        <a href="dashboard.php">🏠 Go to Dashboard</a>
    </div>

    <?php else: ?>
    <!-- MAIN FORM -->
    <div class="form-card">
        <form action="submit_appointment_request.php" method="POST" id="bookingForm">

            <!-- Child -->
            <div class="section-title">👶 Select Child</div>
            <div class="form-row">
                <div class="form-group full">
                    <label class="field-label">Child <span class="req">*</span></label>
                    <select name="child_id" required>
                        <option value="">-- Select Child --</option>
                        <?php
                        mysqli_data_seek($result_children, 0);
                        while($child = mysqli_fetch_assoc($result_children)):
                            $ad = floor((time() - strtotime($child['date_of_birth'])) / 86400);
                            $ay = floor($ad/365); $am = floor(($ad%365)/30);
                            $as = $ay > 0 ? "{$ay}y {$am}m" : "{$am} months";
                        ?>
                        <option value="<?php echo (int)$child['child_id']; ?>">
                            <?php echo htmlspecialchars($child['full_name']); ?>
                            (<?php echo $as; ?>, <?php echo htmlspecialchars($child['gender']); ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Hospital & Vaccine -->
            <div class="section-title">🏥 Hospital & Vaccine</div>
            <div class="form-row">

                <div class="form-group full">
                    <label class="field-label">Hospital <span class="req">*</span></label>
                    <select name="hospital_id" required>
                        <option value="">-- Select Hospital --</option>
                        <?php
                        mysqli_data_seek($result_hospitals, 0);
                        while($h = mysqli_fetch_assoc($result_hospitals)):
                        ?>
                        <option value="<?php echo (int)$h['hospital_id']; ?>">
                            🏥 <?php echo htmlspecialchars($h['hospital_name']); ?>
                            — <?php echo htmlspecialchars($h['city']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label">Vaccine <span class="req">*</span></label>
                    <select name="vaccine_id" id="vaccine_id" required onchange="showVaccineInfo(this)">
                        <option value="">-- Select Vaccine --</option>
                        <?php
                        $vjs = [];
                        mysqli_data_seek($result_vaccines, 0);
                        while($v = mysqli_fetch_assoc($result_vaccines)):
                            $vjs[$v['vaccine_id']] = [
                                'name' => $v['vaccine_name'],
                                'code' => $v['vaccine_code'],
                                'desc' => $v['description'],
                                'mfr'  => $v['manufacturer']
                            ];
                        ?>
                        <option value="<?php echo (int)$v['vaccine_id']; ?>">
                            <?php echo htmlspecialchars($v['vaccine_name']); ?>
                            (<?php echo htmlspecialchars($v['vaccine_code']); ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="vaccine-info-box">
                        <strong id="vi-name"></strong>
                        <span id="vi-desc"></span>
                        <span id="vi-mfr" style="display:block;margin-top:3px;font-size:12px;opacity:.85;"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="field-label">Dose Number <span class="req">*</span></label>
                    <select name="dose_number" required>
                        <option value="">-- Select Dose --</option>
                        <option value="1">Dose 1 — First</option>
                        <option value="2">Dose 2 — Second</option>
                        <option value="3">Dose 3 — Third</option>
                        <option value="4">Dose 4 — Booster</option>
                    </select>
                    <div class="dose-hint">Select the dose number for this vaccine</div>
                </div>

            </div>

            <!-- Date & Time -->
            <div class="section-title">📆 Preferred Date & Time</div>
            <div class="form-row">

                <div class="form-group">
                    <label class="field-label">Preferred Date <span class="req">*</span></label>
                    <input type="date" name="preferred_date" id="preferred_date"
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                           required>
                    <div class="dose-hint">Select a date within the next 60 days</div>
                </div>

                <div class="form-group">
                    <label class="field-label">Preferred Time <span class="req">*</span></label>

                    <!-- Hidden time input — value set by time slot clicks OR manual input -->
                    <input type="time" name="preferred_time" id="preferred_time"
                           style="display:none;" required>

                    <div class="time-slots">
                        <div class="time-slot" onclick="selectTime('09:00',this)">9:00 AM</div>
                        <div class="time-slot" onclick="selectTime('10:00',this)">10:00 AM</div>
                        <div class="time-slot" onclick="selectTime('11:00',this)">11:00 AM</div>
                        <div class="time-slot" onclick="selectTime('12:00',this)">12:00 PM</div>
                        <div class="time-slot" onclick="selectTime('14:00',this)">2:00 PM</div>
                        <div class="time-slot" onclick="selectTime('15:00',this)">3:00 PM</div>
                        <div class="time-slot" onclick="selectTime('16:00',this)">4:00 PM</div>
                    </div>
                    <div class="dose-hint" style="margin-top:8px;">👆 Click a time slot above to select</div>
                    <div class="time-error" id="timeError">⚠️ Please select a time slot.</div>
                </div>

            </div>

            <!-- Notes -->
            <div class="section-title">📝 Additional Notes</div>
            <div class="form-row">
                <div class="form-group full">
                    <label class="field-label">Parent Notes (Optional)</label>
                    <textarea name="parent_notes"
                              placeholder="Any special instructions, allergies or concerns for the hospital..."></textarea>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" name="submit_request" value="1" class="btn-submit" id="submitBtn">
                📅 Submit Appointment Request
            </button>

        </form>
    </div>
    <?php endif; ?>

</div><!-- end page-wrapper -->

<script>
// ── Vaccine data from PHP ──
const vaccineData = <?php echo json_encode($vjs ?? []); ?>;

// ── Show vaccine info box ──
function showVaccineInfo(sel) {
    const id  = sel.value;
    const box = document.getElementById('vaccine-info-box');
    if(id && vaccineData[id]) {
        const v = vaccineData[id];
        document.getElementById('vi-name').textContent = v.name + (v.code ? ' (' + v.code + ')' : '');
        document.getElementById('vi-desc').textContent = v.desc || 'No description available.';
        document.getElementById('vi-mfr').textContent  = v.mfr  ? '🏭 ' + v.mfr : '';
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

// ── Select time slot ──
function selectTime(time, el) {
    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('preferred_time').value = time;
    document.getElementById('timeError').style.display = 'none';
}

// ── Form submit validation ──
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const timeVal = document.getElementById('preferred_time').value;

    // Check time selected
    if(!timeVal) {
        e.preventDefault();
        document.getElementById('timeError').style.display = 'block';
        document.querySelector('.time-slots').scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }

    // Prevent double submit without disabling (disabled removes POST value)
    const btn = document.getElementById('submitBtn');
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.6';
    btn.innerHTML = '⏳ Submitting... Please wait';
});
</script>

<script>
// Dismiss approval alert via AJAX (mark as read without page reload)
function dismissAlert(notifId) {
    const el = document.getElementById('alert-' + notifId);
    if(!el) return;
    el.style.transition = 'all .3s ease';
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px)';
    setTimeout(() => {
        el.remove();
        // Also POST to mark as read in DB
        const fd = new FormData();
        fd.append('dismiss_notification', '1');
        fd.append('notification_id', notifId);
        fetch('book_appointment.php', { method:'POST', body: fd });
    }, 300);
}
</script>

</body>
</html>