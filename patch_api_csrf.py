import os, re

api_dir = r'c:\xampp\htdocs\dashboard\DigiWash\api'
files = ['admin.php', 'create_marketplace_order.php', 'delivery.php', 'marketplace_orders.php', 'marketplace_products.php', 'orders.php', 'payments.php', 'products.php', 'staff_requests.php', 'update_marketplace_status.php', 'user.php']

new_logic = """$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token']
    ?? $headers['x-csrf-token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? (is_array($data ?? null) ? ($data['csrf_token'] ?? '') : '')
    ?? (is_array($body ?? null) ? ($body['csrf_token'] ?? '') : '')
    ?? $_POST['csrf_token']
    ?? '';
"""

for f in files:
    path = os.path.join(api_dir, f)
    with open(path, 'r', encoding='utf-8') as file:
        content = file.read()
    
    match = re.search(r'\$headers\s*=\s*getallheaders\(\);.*?(?=(\$serverCsrf|if\s*\(!|//\s*CSRF|if\s*\(empty\())', content, re.DOTALL)
    if match:
        content = content[:match.start()] + new_logic + content[match.end():]
        with open(path, 'w', encoding='utf-8', newline='') as file:
            file.write(content)
        print(f"Patched {f}")
    else:
        print(f"Could not patch {f}")
