<?php

	$cookiejar = getenv("PHP_PRIVATE") . "/mewe-cookies/name-history.txt";
	$error = "";
	$grouped_names = [];
	$date_format = "F Y";
    $post_blacklist = explode("\n", file_get_contents("blacklist.txt"));

	if (isset($_POST["user"])) get_user($_POST["user"]);

	function get_user($invite_link) {	
		global $error, $grouped_names, $post_blacklist;

		// Check if authenticated
		$auth = mewe_request("GET", "/api/v3/auth/identify");
		if (!$auth || $auth["authenticated"] === false || $auth["confirmed"] === false) {
		    $error = "The bot is currently down. If this persists, please contact ReimarPB";
		    return;
        }

		// Parse link and get invite code
		if (!preg_match("/^https:\/\/mewe\.com\/i(-front)?\/([^\/]+)/", $invite_link, $matches)) {
			$error = "Invalid link";
			return;
		}
		$invite_id = $matches[2];

		$names = [];	

		// Get current name
		$user = mewe_request("GET", "/api/v2/mycontacts/user?inviteId=$invite_id");
		if (!$user) {
			$error = "User does not exist";
			return;
		}
		
		// Search for user ID and get all results	
		$offset = 0;
		$more_results = true;
		$search_results = [];
		while ($more_results) {
			$response = mewe_request("GET", "/api/v3/desktop/search/posts?query=${user["id"]}&offset=$offset&limit=50&highlight=true");
			$search_results = array_merge($search_results, $response["results"]); 
			$more_results = $response["hasMoreResults"] && count($response["results"]) > 0;
			$offset += count($response["results"]);
		}
		
		// Look for mentions inside the results and save them along with the timestamp
		foreach ($search_results as $result) {

			if (isset($result["post"])) {
				$item = $result["post"];
				$text = $item["textPlain"];
			} else if (isset($result["comment"])) {
				$item = $result["comment"];
				$text = $item["text"];
			} else continue;

			if (in_array($item["sharedPostId"], $post_blacklist)) continue;

			$timestamp = $item["editedAt"] ?: $item["createdAt"];

			if (
                preg_match("/@{{u_${user["id"]}}(.+?)}/", $text, $matches) && // Get user ID
				strpos($matches[1], "@{{u_") === false && // Ignore glitchy names
                !in_array($item["id"], array_column($names, "id")) // Make sure there are no duplicates
            ) {
				$names[] = [
					"name" => $matches[1],
					"timestamp" => $timestamp,
                    "id" => $item["sharedPostId"]
				];
			}
		}

		// Group the same names together
		usort($names, function($a, $b) {
			return $b["timestamp"] - $a["timestamp"];
		});

		$grouped_names[$user["name"]] = [
			"current" => true,
			"timestamps" => [],
            "ids" => []
		];

		foreach ($names as $name) {
			if (!$grouped_names[$name["name"]])
			    $grouped_names[$name["name"]] = [
					"current" => $name["name"] == $user["name"],
                    "timestamps" => [],
                    "ids" => []
                ];

			$grouped_names[$name["name"]]["timestamps"][] = $name["timestamp"];
			$grouped_names[$name["name"]]["ids"][] = $name["id"];
		}

	}

	function mewe_request($method, $path, $body = null) {
		global $cookiejar, $error;

		preg_match("/csrf-token\t(.+?)$/m", file_get_contents($cookiejar), $matches);
		$csrf_token = $matches[1];

		$curl = curl_init("https://mewe.com$path");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $cookiejar);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $cookiejar);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		//curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1:8888");
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			"Accept: application/json",
			"Content-Type: application/json",
			"X-CSRF-Token: $csrf_token"
		]);
		if ($body) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

		$response = json_decode(curl_exec($curl), true);
		$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		if ($status != 200) {
			$error = $response["message"];
			return false;
		}	

		curl_close($curl);
		return $response;

	}

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>MeWe Name History</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<style>
			body {
				text-align: center;
				font-family: Arial, sans-serif;
			}
			input {
				width: 100%;
				max-width: 300px;
			}
			#warning {
				color: #9E9E9E;
			}
			#error {
				color: #D50000;
			}
			.name {
				font-size: 2em;
				font-weight: bold;
			}
			.current {
				font-weight: bold;
				color: #00C853;
			}
			.name-container {
				text-align: left;
				margin-top: 20px;
			}
			.name-list {
				display: inline-block;
			}
			footer {
				position: fixed;
                width: 100%;
				bottom: 0;
				left: 50%;
                padding: 10px;
                background-color: white;
				transform: translate(-50%);
			}
			a {
				color: #00B0FF;
				text-decoration: none;
			}
			a:hover {
				text-decoration: underline;
			}
            ul {
                margin: 0;
            }
		</style>
	</head>
	<body>
		<h1>MeWe Name History</h1>
		<form method="POST">
			<label>
				Paste the user's link here:<br>
				<input type="text" name="user" placeholder="https://mewe.com/i/user" value="<?= isset($_POST["user"]) ? $_POST["user"] : "" ?>">
			</label>
			<button type="submit">Go</button>
		</form>
		<p id="warning"><b>Warning:</b> Some names may be missing and dates may be inaccurate</p>
		<p id="error"><?= $error ?></p>	
		<div class="name-list">
			<?php foreach ($grouped_names as $name=>$match): ?>
				<div class="name-container">
					<span class="name"><?= $name ?></span>
					<br>
					<?php if ($match["current"]): ?>
						<span class="current">[Current]</span>
					<?php endif; ?>

                    <?php if (!empty($match["timestamps"])): ?>

                        <details>
                            <summary>
                                <?php if (count($match["timestamps"]) == 1): ?>
                                    <b>1</b> match found in <b><?= date($date_format, $match["timestamps"][0]) ?></b>
                                <?php endif; ?>

                                <?php if (count($match["timestamps"]) > 1): ?>
                                    <b><?= count($match["timestamps"]) ?></b> matches found between <b><?= date($date_format, end($match["timestamps"])) ?></b> and <b><?= date($date_format, $match["timestamps"][0]) ?></b>
                                <?php endif; ?>
                            </summary>
                            <ul>
                                <?php foreach ($match["ids"] as $i=>$id): ?>
                                    <li><a href="https://mewe.com/myworld/show/<?= $id ?>" target="_blank"><?= date("Y-m-d H:i:s", $match["timestamps"][$i]) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>

                    <?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
        <div style="height: 40px"></div>
		<footer>
			Created by <a href="https://mewe.com/i/reimarpb" target="_blank">ReimarPB</a> &bull; Send a message if you find any bugs
		</footer>
	</body>
</html>

