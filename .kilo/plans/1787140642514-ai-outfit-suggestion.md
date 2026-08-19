# AI Outfit Suggestion Feature Plan

## Goal
Add a new AI-powered outfit suggestion page that generates curated looks based on weather + occasion, using a local/free LLM (Ollama) and the user's wardrobe.

## Decisions (Resolved)
- **LLM provider:** Ollama (local, free). Default model: `llama3.2` or `mistral`. Fallback to rule-based if Ollama is unreachable.
- **Occasions:** Casual, Work/Office, Formal/Event, Workout, Date Night, Travel, Beach/Pool (displayed as chips).
- **Location:** New dedicated page `recommendations.php`, linked from main nav as "AI Style".
- **Response contract:** LLM returns JSON with `items` (from wardrobe only), `vibe`, and `styling_tip`.

## Tasks

### 1. Database Migration
**File:** `database/closetiq.sql`
- Add `occasion VARCHAR(50) NULL` to `outfit_history`
- Add `ai_generated TINYINT(1) DEFAULT 0` to `outfit_history`
- Backfill: existing rows keep NULL/0

### 2. New API Endpoint
**File:** `api/ai-outfit.php` (new)
- `POST` only, `require_login()`
- Input: `city` (string), `occasion` (string), `include_weather` (bool)
- Flow:
  1. Validate inputs
  2. If `include_weather`, fetch weather via OpenWeatherMap (reuse logic from `api/weather.php` / `outfits.php`)
  3. Fetch user's wardrobe items (`clothing_items` where `user_id = :uid`)
  4. Build LLM prompt with wardrobe items, weather, and occasion
  5. Call Ollama at `http://localhost:11434/api/chat` with JSON body:
     ```json
     {
       "model": "llama3.2",
       "stream": false,
       "format": "json",
       "messages": [
         {
           "role": "system",
           "content": "You are a fashion stylist. Select 2-4 items ONLY from the user's wardrobe list..."
         },
         {
           "role": "user",
           "content": "Weather: ...\nOccasion: ...\nWardrobe: ..."
         }
       ]
     }
     ```
  6. Parse LLM response; if JSON invalid, fallback to rule-based selection (same logic as `outfits.php`)
  7. Save to `outfit_history` with `occasion` and `ai_generated = 1`
  8. Return `{ success: true, data: { items, vibe, styling_tip, weather } }` or `{ success: false, error: "..." }`

**Prompt contract (system message):**
- Instruct LLM to return ONLY valid JSON
- Fields: `items` (array of `{name, category, color}`), `vibe` (string), `styling_tip` (string)
- Constraint: items must come exclusively from the provided wardrobe list
- If LLM hallucinates items not in wardrobe, the PHP layer validates and strips them before returning

### 3. New Page
**File:** `recommendations.php` (new)
- Follows existing include pattern (`config/database.php`, `includes/auth.php`, `includes/header.php`, `includes/footer.php`)
- Layout: two columns on desktop, stacked on mobile
  - **Left column:** Form
    - City input (default "Nairobi")
    - Include weather checkbox (checked by default)
    - Occasion chips (7 buttons, toggleable, exactly one selected)
    - "Generate AI Look" submit button
    - Loading state / spinner while waiting for API
  - **Right column:** Result display
    - Empty state: "Select an occasion and generate your AI-powered look"
    - Success state:
      - **The Look:** outfit item cards with images (same card style as `outfits.php`)
      - **The Vibe:** accent-colored info card
      - **Styling Tip:** warning-colored info card
      - Weather badge if weather included
    - Error state: alert with message (Ollama unreachable, no wardrobe items, etc.)

**Client-side JS (inline in page):**
- `fetch('api/ai-outfit.php', { method: 'POST', body: FormData })`
- Show/hide loading spinner
- Render result cards on success
- Chip selection UX: clicking a chip selects it and deselects others

### 4. Navigation Update
**File:** `includes/header.php`
- Add nav item: `<li><a href="recommendations.php" class="<?php echo $currentPage === 'recommendations.php' ? 'active' : ''; ?>">AI Style</a></li>`
- Place after "Outfits" or before "History"

### 5. CSS Additions
**File:** `css/style.css`
- `.chip-group`: flex row, gap, flex-wrap
- `.chip`: padding, border-radius, cursor pointer, border, background white
- `.chip.selected`: primary background, white text
- `.occasion-grid`: responsive grid for chips (auto-fill, minmax)
- `.ai-loading`: spinner animation
- `.vibe-card`, `.styling-tip-card`: distinct background/border colors
- `.ai-result-section`: spacing between vibe and tip cards
- Responsive adjustments at `768px` and `480px`

### 6. Error Handling & Fallbacks
- If Ollama is not reachable (connection refused / timeout), return clear error: "AI service unavailable. Please ensure Ollama is running locally."
- If LLM returns invalid JSON after 2 retries, fallback to the existing rule-based random outfit generator from `outfits.php` and set `ai_generated = 0`
- If user has no wardrobe items, return error prompting them to add items first
- API timeout: 60 seconds (LLM generation can be slow)

### 7. Validation Checklist
- [ ] `recommendations.php` loads with nav active state
- [ ] Occasion chips toggle correctly (single select)
- [ ] Form submits to `api/ai-outfit.php` and displays loading state
- [ ] With Ollama running: returns curated look with vibe + styling tip
- [ ] With Ollama stopped: shows graceful error message
- [ ] Empty wardrobe shows "add items first" error
- [ ] Weather badge appears when include_weather is checked
- [ ] Saved to `outfit_history` with `occasion` and `ai_generated = 1`
- [ ] Responsive layout works on mobile

## Out of Scope
- User accounts for multiple LLM providers / API key management UI
- Caching LLM responses (always generate fresh)
- Advanced color-coordination rules beyond what the LLM handles
- Outfit rating / feedback loop to improve suggestions
- Ability to save AI suggestions separately from regular history
