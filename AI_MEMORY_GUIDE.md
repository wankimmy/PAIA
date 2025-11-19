# AI Memory & Behavior Tracking Guide

## Overview

The Personal AI Assistant now includes a comprehensive AI memory system that learns about users over time, tracks behavior patterns, and provides a personalized "best friend" experience.

## Features

### 1. User Profile
Stores basic information about the user:
- Name/Nickname
- Pronouns
- Bio
- Timezone
- Preferred tone (friendly, professional, casual)
- Preferred answer length (short, normal, detailed)

### 2. AI Memories
Persistent facts the AI remembers about the user, organized by category:
- **personal_fact**: Personal information (e.g., "You're a software engineer")
- **preference**: User preferences (e.g., "You prefer detailed explanations")
- **habit**: Learned behaviors (e.g., "You usually complete tasks in the evening")
- **goal**: User goals (e.g., "Primary goal: productivity")
- **boundary**: Topics to avoid (e.g., "Topics to avoid: work stress")

Each memory has:
- Category
- Key (unique identifier)
- Value (short sentence)
- Importance (1-5 scale)
- Source (user_input, ai_inferred, system)

### 3. Behavior Tracking
Automatically tracks user interactions:
- Chat messages
- Voice commands
- Task completions (with time of day)
- Reminder fires
- Reminder snoozes

### 4. Habit Aggregation
Daily scheduled command analyzes behavior patterns and creates habit memories:
- Most common task completion times
- Reminder snooze patterns
- Task completion delays

## Database Schema

### user_profiles
- Stores basic user information
- One-to-one with users

### ai_memories
- Stores persistent facts about users
- Indexed by user_id, category, and importance
- Can be viewed/edited/deleted by user

### ai_interactions
- Logs all user interactions
- Used for behavior analysis
- Metadata stored as JSON

## API Endpoints

### Profile
- `GET /api/profile` - Get user profile
- `PUT /api/profile` - Update user profile

### Memories
- `GET /api/ai/memories` - List memories (optional: ?category=X&limit=Y)
- `POST /api/ai/memories` - Create memory
- `PUT /api/ai/memories/{id}` - Update memory
- `DELETE /api/ai/memories/{id}` - Delete memory

## Configuration

### Environment Variables

```env
# Enable/disable automatic memory extraction from conversations
AI_AUTO_MEMORY_ENABLED=false
```

When enabled, the AI will automatically extract new memories from conversations. When disabled, memories are only created manually or through behavior aggregation.

## How It Works

### 1. Onboarding
On first login, users complete an onboarding flow that:
- Collects profile information
- Sets preferences
- Creates initial memories

### 2. AI Chat
Every chat message:
- Loads user profile and memories
- Builds personalized system prompt
- Records interaction
- Optionally extracts new memories (if enabled)

### 3. Behavior Tracking
- Task completions are tracked with timestamp
- Reminder fires are logged
- Daily aggregation analyzes patterns

### 4. Memory Management
- Users can view all memories in Settings
- Users can edit or delete any memory
- Memories are organized by category
- Importance levels help prioritize

## Privacy & Safety

### Data Protection
- All memory stored locally in SQLite
- No external cloud services
- User has full control over all memories

### Safety Filters
- Automatic filtering of sensitive data (passwords, secrets)
- User can delete any memory anytime
- Memories are transparent and visible

### Best Practices
- Memories are short, factual sentences
- No raw sensitive content stored
- User boundaries are respected
- AI asks permission for sensitive topics

## Usage Examples

### Manual Memory Creation
```javascript
// Via API
POST /api/ai/memories
{
  "category": "personal_fact",
  "key": "favorite_coffee",
  "value": "You prefer black coffee in the morning.",
  "importance": 3
}
```

### Viewing Memories
```javascript
// Get all memories
GET /api/ai/memories

// Get by category
GET /api/ai/memories?category=habit

// Limited results
GET /api/ai/memories?limit=10
```

### AI Uses Memory
The AI automatically uses memories in every conversation:
- Personalizes greetings with nickname
- Matches preferred tone and answer length
- Respects boundaries
- References learned habits when making suggestions

## Scheduled Commands

### Habit Aggregation
Runs daily to analyze behavior:
```bash
php artisan habits:aggregate
```

Creates habit memories like:
- "You usually complete tasks in the evening"
- "You frequently snooze reminders"

## Frontend

### Settings Page
- View all memories organized by category
- Edit memory values and importance
- Delete unwanted memories
- Update profile information

### Onboarding
- Collects profile data
- Sets initial preferences
- Creates first memories
- One-time flow on first login

## Design Decisions

1. **Transparency**: All memories are visible and editable
2. **User Control**: Users can delete any memory
3. **Privacy First**: No external services, all local
4. **Safety**: Automatic filtering of sensitive data
5. **Flexibility**: Optional auto-memory can be disabled
6. **Personalization**: AI adapts to user preferences

## Limitations

1. **Memory Extraction**: Auto-extraction is experimental and can be disabled
2. **Behavior Analysis**: Simple time-of-day patterns, can be extended
3. **Storage**: SQLite limits, but sufficient for personal use
4. **Context Window**: Only top 20 memories by importance are used in prompts

## Advanced Features

### Behavior Analysis & Insights

The system now includes sophisticated behavior analysis through the `BehaviorAnalysisService`:

- **Task Completion Patterns**: Analyzes when you complete tasks, which tags you use most, overdue rates, and consistency patterns
- **Reminder Patterns**: Tracks reminder firing and snooze rates, most active hours
- **Chat Patterns**: Analyzes message length, response patterns, and action-oriented behavior
- **Behavior Insights API**: Access detailed insights via `/api/behavior/insights`

The `AggregateHabits` command now uses advanced pattern recognition to automatically create memories about:
- Peak productivity times
- Most productive days of the week
- Frequently used task tags
- Communication preferences
- Task management habits

### Memory Search & Filtering

The AI Memory API now supports comprehensive search and filtering:

**Query Parameters:**
- `search`: Search in memory value or key (case-insensitive)
- `category`: Filter by category (personal_fact, preference, habit, goal, boundary)
- `source`: Filter by source (user_input, ai_inferred, system)
- `min_importance`: Filter by minimum importance (1-5)
- `sort_by`: Sort by importance, updated_at, created_at, or category
- `sort_order`: asc or desc
- `limit`: Limit results (max 100)

**Example API Calls:**
```bash
# Search for memories containing "coffee"
GET /api/ai/memories?search=coffee

# Filter by category and importance
GET /api/ai/memories?category=habit&min_importance=4

# Sort by most recently updated
GET /api/ai/memories?sort_by=updated_at&sort_order=desc
```

**Frontend UI:**
The Settings page includes a comprehensive filter interface with:
- Real-time search input
- Category dropdown filter
- Source filter
- Importance filter
- Clear filters button
- Results counter

### Advanced Pattern Recognition

The system automatically recognizes and learns from:

1. **Temporal Patterns**:
   - Time of day preferences (morning, afternoon, evening, night)
   - Day of week patterns (most productive days)
   - Consistency metrics

2. **Task Patterns**:
   - Tag usage frequency
   - Completion timing
   - Overdue behavior
   - Task category preferences

3. **Communication Patterns**:
   - Message length preferences
   - Action-oriented vs conversational
   - Response style preferences

4. **Reminder Patterns**:
   - Snooze frequency
   - Most active reminder hours
   - Response rates

These patterns are automatically converted into AI memories with appropriate importance levels and categories.

## Future Enhancements

- Memory export/import
- Memory sharing (if multi-user)
- Predictive insights based on historical patterns
- Custom pattern recognition rules

