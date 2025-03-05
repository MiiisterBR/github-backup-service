# GitHub Backup Service

A simple PHP service that backs up repositories from a GitHub organization or user account. This project uses the GitHub API to fetch repositories (both public and private in user mode) and allows you to download backups. The service also supports optional fetching of collaborators and offers a dashboard with various management features.

Demo URL: https://gbs.misterbr.ir

> **Important Note:**  
> If you are using organization mode, **you must enter the organization name** in the designated input field on the dashboard. Otherwise, the removal of collaborators from repositories may fail due to insufficient access permissions.

## Features

- **Backup Repositories:**  
  Back up all repositories from a GitHub organization or user account. The service supports pagination to retrieve all repositories.

- **Fetch Collaborators:**  
  Optionally fetch collaborators for each repository.
- **Manage Collaborators:**  
  Update or remove collaborators (both globally and repository-specific).

- **Dashboard:**  
  A single-page dashboard with statistics and operations, built using Tailwind CSS and jQuery.

- **Local Data Storage:**  
  Uses IndexedDB to store repository status, such as last backup date and ignored state.

- **Security:**  
  Data is encrypted using a salt. **Note:** The encryption salt is defined in the configuration and JavaScript files.

## Installation

### Prerequisites

- PHP 7.0 or later
- A web server (Apache, Nginx, or PHP’s built-in server)
- cURL enabled in PHP
- Git (to clone the repository)

### Local Setup

1. **Clone the Repository:**

   ```bash
   git clone https://github.com/yourusername/github-backup-service.git
   cd github-backup-service
   ```

2. **Configure the Project:**

   Open the `inc/config.php` file and update the configuration values as needed. For example:

   ```php
   <?php
   // Encryption salt for the username cookie
   const ENCRYPTION_SALT = '16N!z<X0*85-NuPq';
   
   // Check if the encrypted username cookie is set
   if (isset($_COOKIE['encrypted_username'])) {
   
      // Decode the encrypted username and remove the salt
      $decodedUsername = base64_decode($_COOKIE['encrypted_username']);
      $username = str_replace(ENCRYPTION_SALT, '', $decodedUsername);
   
   } else {
      // Fallback username if the cookie is not set
      $username = 'MiiisterBR';
   }
   
   // Define the backups directory path
   define("BACKUPS_DIR", realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'backups') . DIRECTORY_SEPARATOR . $username);
   ```

3. **Run the PHP Built-in Server (for testing):**

   ```bash
   php -S localhost:8081
   ```

   Open your browser and navigate to [http://localhost:8081](http://localhost:8081) to view the dashboard.

### Deployment on a Live Server

1. **Upload Files:**  
   Upload the entire project folder to your web server (e.g., using FTP or a Git deployment).

2. **Configure Web Server:**  
   Ensure your server is configured to serve PHP files. For Apache, make sure you have an `.htaccess` file (if needed) to handle clean URLs.

3. **Access the Service:**  
   Navigate to the public URL of your deployed project.

## Security Recommendations

### Encryption Salt

The project uses a static encryption salt defined in the configuration file (`inc/config.php`) and referenced in your JavaScript (app.js) as:

```js
const encryptionSalt = "16N!z<X0*85-NuPq";
```

#### Recommendations:

- **Do Not Expose in Public:**  
  The encryption salt should be kept secret. If possible, restrict direct access to your configuration files via your web server settings.

- **Environment Variables:**  
  For enhanced security, consider moving sensitive values (like the encryption salt) to environment variables or a secure configuration system that is not part of your source code repository.

- **Documentation:**  
  In your README, instruct users to change the encryption salt in both `inc/config.php` and `assets/js/app.js` to a new secure value after installation. For example:

  > **Security Notice:**  
  > After cloning the repository, change the value of `ENCRYPTION_SALT` in `inc/config.php` and update the corresponding `encryptionSalt` value in `assets/js/app.js`. Do not use the default salt in production.

### Additional Security Measures

- **HTTPS:**  
  Always run the service over HTTPS to prevent interception of sensitive data.

- **Access Control:**  
  Consider adding additional access controls to your service endpoints, ensuring that only authorized users can perform backups or manage collaborators.

## Usage

1. **Enter GitHub Credentials:**  
   On the dashboard, enter your GitHub username and a Personal Access Token (PAT). Make sure the PAT has the necessary permissions (all except deletion).

2. **Fetching Repositories:**  
   Choose whether to fetch repositories from an organization or as a user. If you select organization mode, check the "Use Organization Repositories" box and enter the organization name.

3. **Backup Operations:**  
   Use the "Backup All" button to initiate a sequential backup of your repositories. You can cancel the process with the "Cancel Backup Process" button.

4. **Manage Collaborators:**  
   In the "Manage Collaborators" section, use the provided dropdowns to update or remove collaborator access.

5. **Clear Stored Data:**  
   The "Clear Stored Data" button resets all locally stored data (cookies and IndexedDB) and refreshes the page.

## License

This project is licensed under the MIT License.
