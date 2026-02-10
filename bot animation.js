function triggerBotTurn() {
    const botLoading = document.getElementById("bot-loading");

    // Show loading message
    botLoading.style.display = "block";

    // Wait 1.5 seconds, then make bot move
    setTimeout(() => {
        botLoading.style.display = "none"; // Hide the message
        botPlay(); // Call the bot's actual move function
    }, 1500);
}
