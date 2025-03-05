<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Backup Service Dashboard</title>
    <!-- jQuery Library -->
    <script src="./assets/js/jquery.js"></script>
    <!-- Tailwind CSS (or Tailwind script if being used in this way) -->
    <script src="./assets/js/tailwindcss.js"></script>
</head>
<body class="bg-gray-100 p-4">
<!-- Cancel Loading Button (hidden by default) -->
<div id="cancelLoadingBtn" class="fixed bottom-5 left-5 z-50 hidden">
    <button class="bg-red-500 text-white px-4 py-2 rounded text-sm cursor-pointer">
        Cancel Loading
    </button>
</div>

<div class="w-full mx-auto bg-white p-4 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">Backup Service Dashboard</h1>

    <!-- Loading Indicator -->
    <div id="loading" class="hidden fixed inset-0 flex items-center justify-center bg-gray-700 bg-opacity-50 z-40">
        <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-blue-500"></div>
    </div>

    <!-- Instructions for creating a PAT -->
    <div class="mb-4 p-3 bg-blue-100 border border-blue-300 rounded">
        <p>
            To create a Personal Access Token (PAT), please visit
            <a href="https://github.com/settings/tokens" target="_blank" class="text-blue-600 underline">
                GitHub Settings &gt; Developer settings &gt; Personal access tokens
            </a>.
            Make sure to grant all permissions except deletion.
        </p>
    </div>

    <!-- User Credentials Form -->
    <div class="mb-4" id="loginFormContainer">
        <h2 class="text-xl font-semibold mb-2">Enter Your GitHub Credentials</h2>
        <form id="userForm" class="mt-1">
            <div class="mb-2">
                <label class="block mb-1 text-sm">GitHub Username:</label>
                <input type="text" id="username" class="border p-2 w-full text-sm" required>
            </div>
            <div class="mb-2">
                <label class="block mb-1 text-sm">GitHub Token (PAT):</label>
                <input type="text" id="token" class="border p-2 w-full text-sm" required>
            </div>
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded text-sm">Save and Continue</button>
        </form>
    </div>

    <!-- Dashboard (displayed after login) -->
    <div id="dashboard" class="hidden">
        <!-- Stats Cards -->
        <div class="mb-4">
            <div class="flex flex-col md:flex-row md:space-x-4 space-y-4 md:space-y-0">
                <!-- Total Repositories Card -->
                <div class="flex items-center p-3 bg-indigo-600 text-white rounded shadow flex-1">
                    <div class="mr-3">
                        <!-- Icon for total repositories -->
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 7v10a2 2 0 002 2h12a2 2 0 002-2V7M4 7l8-4 8 4"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" id="repoCount">0</div>
                        <div class="text-sm">Total Repositories</div>
                    </div>
                </div>
                <!-- Repositories Backed Up Card -->
                <div class="flex items-center p-3 bg-green-600 text-white rounded shadow flex-1">
                    <div class="mr-3">
                        <!-- Icon for backed up repositories -->
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" id="backupCount">0</div>
                        <div class="text-sm">Repositories Backed Up</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operation Groups (3 columns) -->
        <div class="mb-4 flex flex-col md:flex-row md:space-x-4 space-y-4 md:space-y-0">
            <!-- Group 1: Repository Fetch -->
            <div class="flex-1 p-3 border rounded shadow bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h3 class="font-semibold text-base mb-2 md:mb-0">Repository Fetch</h3>
                    <div class="flex items-center space-x-2">
                        <button id="fetchReposBtn" class="bg-blue-500 text-white px-3 py-2 rounded text-xs">Fetch
                            Repositories
                        </button>
                        <div class="flex items-center">
                            <input type="checkbox" id="fetchCollaboratorsCheckbox" class="form-checkbox mr-1">
                            <label for="fetchCollaboratorsCheckbox" class="text-xs">Fetch Collaborators</label>
                        </div>
                    </div>
                </div>
                <!-- Organization Checkbox and Input -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mt-2">
                    <div class="flex items-center">
                        <input type="checkbox" id="orgCheckbox" class="form-checkbox mr-1">
                        <label for="orgCheckbox" class="text-xs">Use Organization Repositories</label>
                    </div>
                </div>
                <div id="orgNameContainer" class="mt-2" style="display:none;">
                    <label class="block text-xs">Organization Name:</label>
                    <input type="text" id="orgNameInput" class="border p-1 text-xs w-full"
                           placeholder="Enter organization name">
                </div>
            </div>
            <!-- Group 2: Backup Operations -->
            <div class="flex-1 p-3 border rounded shadow bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h3 class="font-semibold text-base mb-2 md:mb-0">Backup Operations</h3>
                    <div class="flex items-center space-x-2">
                        <button id="backupAllBtn" class="bg-purple-500 text-white px-3 py-2 rounded text-xs">Backup
                            All
                        </button>
                        <button id="cancelBackupProcessBtn" class="bg-red-500 text-white px-3 py-2 rounded text-xs"
                                style="display:none;">Cancel Backup Process
                        </button>
                    </div>
                </div>
            </div>
            <!-- Group 3: Maintenance -->
            <div class="flex-1 p-3 border rounded shadow bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h3 class="font-semibold text-base mb-2 md:mb-0">Maintenance</h3>
                    <div>
                        <button id="clearStorage" class="bg-orange-500 text-white px-3 py-2 rounded text-xs">Clear
                            Stored Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collaborator Management Section (Combined Global/Repo-specific) -->
        <div id="collaboratorManagement" class="mb-4 p-3 border rounded shadow bg-gray-50">
            <h2 class="text-xl font-semibold mb-2">Manage Collaborators</h2>
            <label class="text-sm block mb-1">Select Repository:</label>
            <select id="repoSelect" class="border p-2 text-sm w-full mb-2">
                <option value="all">All Repositories</option>
                <!-- Repository options will be populated dynamically -->
            </select>
            <label class="text-sm block mb-1">Select Collaborator:</label>
            <select id="collaboratorSelect" class="border p-2 text-sm w-full mb-2">
                <!-- Unique collaborator options will be populated dynamically -->
            </select>
            <div class="flex space-x-2">
                <select id="accessLevelSelect" class="border p-2 text-sm">
                    <option value="admin">Admin</option>
                    <option value="push">Write</option>
                    <option value="pull">Read</option>
                    <option value="triage">Triage</option>
                    <option value="maintain">Maintain</option>
                </select>
                <button id="updateAccessBtn" class="bg-blue-500 text-white px-3 py-2 rounded text-xs">Update Access
                </button>
                <button id="removeCollabBtn" class="bg-red-500 text-white px-3 py-2 rounded text-xs">Remove
                    Collaborator
                </button>
            </div>
        </div>

        <!-- Repository List Section -->
        <div id="repoList" class="space-y-3">
            <!-- Each repository will be displayed as a block -->
        </div>
    </div>
</div>

<!-- Script to toggle organization name input visibility -->
<script>
    $("#orgCheckbox").change(function () {
        if ($(this).is(":checked")) {
            $("#orgNameContainer").show();
        } else {
            $("#orgNameContainer").hide();
        }
    });
</script>
<!-- Main Application Script -->
<script src="./assets/js/app.js?v1.0.0"></script>
</body>
</html>